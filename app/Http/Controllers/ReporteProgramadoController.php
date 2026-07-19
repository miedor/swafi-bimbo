<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduledReportRequest;
use App\Models\ReporteGuardado;
use App\Models\ReporteProgramado;
use App\Services\SafeExceptionReporter;
use App\Services\ScheduledReportService;
use App\Services\SwafiAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteProgramadoController extends Controller
{
    public function __construct(
        private readonly ScheduledReportService $scheduledReports,
        private readonly SwafiAuthorizationService $authorization,
        private readonly SafeExceptionReporter $safeExceptions
    ) {
    }

    public function store(StoreScheduledReportRequest $request): RedirectResponse
    {
        $this->authorizeScheduling();
        $userId = $this->userId($request);
        $data = $request->safeScheduleData();

        $savedReport = ReporteGuardado::query()
            ->whereKey($data['reporte_guardado_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $schedule = DB::transaction(function () use ($savedReport, $userId, $data): ReporteProgramado {
            $schedule = ReporteProgramado::withTrashed()
                ->where('reporte_guardado_id', $savedReport->id)
                ->lockForUpdate()
                ->first();

            if (!$schedule) {
                $schedule = new ReporteProgramado([
                    'reporte_guardado_id' => $savedReport->id,
                    'user_id' => $userId,
                ]);
            }

            $schedule->fill([
                'frecuencia' => $data['frecuencia'],
                'dia_semana' => $data['frecuencia'] === 'semanal' ? $data['dia_semana'] : null,
                'dia_mes' => $data['frecuencia'] === 'mensual' ? $data['dia_mes'] : null,
                'hora_local' => $data['hora_local'],
                'zona_horaria' => $data['zona_horaria'],
                'formato' => $data['formato'],
                'destinatarios' => $data['destinatarios'],
                'activo' => $data['activo'],
                'ultimo_estado' => $data['activo'] ? 'programado' : 'suspendido',
                'ultimo_error_referencia' => null,
                'deleted_by' => null,
                'delete_reason' => null,
            ]);

            $schedule->proxima_ejecucion_at = $data['activo']
                ? $this->scheduledReports->nextRunForSchedule($schedule)
                : null;
            $schedule->save();

            if ($schedule->trashed()) {
                $schedule->restore();
            }

            return $schedule->fresh();
        }, 3);

        $this->audit($request, 'REPORTE_PROGRAMADO_GUARDADO', $schedule, [
            'reporte_guardado_id' => $savedReport->id,
            'frecuencia' => $schedule->frecuencia,
            'formato' => $schedule->formato,
            'destinatarios_total' => count($schedule->destinatarios ?? []),
            'activo' => $schedule->activo,
            'proxima_ejecucion_at' => optional($schedule->proxima_ejecucion_at)->toIso8601String(),
        ]);

        return redirect()
            ->route('reportes', ['tipo_reporte' => $savedReport->tipo_reporte])
            ->with('success', 'La programación del reporte se guardó correctamente.');
    }

    public function toggle(Request $request, ReporteProgramado $programacion): RedirectResponse
    {
        $this->authorizeScheduling();
        $userId = $this->userId($request);
        $this->assertOwner($programacion, $userId);

        $validated = $request->validate([
            'activo' => ['required', 'boolean'],
        ], [
            'activo.required' => 'Indica si la programación debe quedar activa o suspendida.',
            'activo.boolean' => 'El estado de la programación no es válido.',
        ]);

        $active = filter_var($validated['activo'], FILTER_VALIDATE_BOOL);

        DB::transaction(function () use ($programacion, $active): void {
            $schedule = ReporteProgramado::query()
                ->whereKey($programacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule->forceFill([
                'activo' => $active,
                'proxima_ejecucion_at' => $active
                    ? $this->scheduledReports->nextRunForSchedule($schedule)
                    : null,
                'ultimo_estado' => $active ? 'programado' : 'suspendido',
                'ultimo_error_referencia' => null,
            ])->save();
        }, 3);

        $programacion->refresh();
        $this->audit($request, 'REPORTE_PROGRAMADO_ESTADO', $programacion, [
            'activo' => $programacion->activo,
            'proxima_ejecucion_at' => optional($programacion->proxima_ejecucion_at)->toIso8601String(),
        ]);

        return redirect()
            ->route('reportes')
            ->with('success', $active
                ? 'La programación del reporte quedó activa.'
                : 'La programación del reporte quedó suspendida.');
    }

    public function destroy(Request $request, ReporteProgramado $programacion): RedirectResponse
    {
        $this->authorizeScheduling();
        $userId = $this->userId($request);
        $this->assertOwner($programacion, $userId);

        $validated = $request->validate([
            'motivo_baja' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo_baja.required' => 'Captura el motivo de la baja de la programación.',
            'motivo_baja.min' => 'El motivo de baja debe tener al menos 10 caracteres.',
            'motivo_baja.max' => 'El motivo de baja no debe superar 500 caracteres.',
        ]);

        DB::transaction(function () use ($programacion, $userId, $validated): void {
            $programacion->forceFill([
                'activo' => false,
                'proxima_ejecucion_at' => null,
                'ultimo_estado' => 'eliminado',
                'deleted_by' => $userId,
                'delete_reason' => trim($validated['motivo_baja']),
            ])->save();
            $programacion->delete();
        }, 3);

        $this->audit($request, 'REPORTE_PROGRAMADO_ELIMINADO', $programacion, [
            'motivo' => trim($validated['motivo_baja']),
        ]);

        return redirect()
            ->route('reportes')
            ->with('success', 'La programación se dio de baja lógicamente y conserva su trazabilidad.');
    }

    private function authorizeScheduling(): void
    {
        abort_unless(
            $this->authorization->canCurrentUser('reportes.programar'),
            403,
            'Tu usuario no tiene permiso para programar reportes.'
        );
    }

    private function assertOwner(ReporteProgramado $schedule, int $userId): void
    {
        abort_unless(
            (int) $schedule->user_id === $userId,
            403,
            'No puedes administrar una programación que pertenece a otro usuario.'
        );
    }

    private function userId(Request $request): int
    {
        $userId = (int) ($request->session()->get('swafi_user_id') ?: $request->user()?->id);

        abort_if($userId <= 0, 403, 'No fue posible identificar al usuario de la sesión.');

        return $userId;
    }

    /**
     * @param array<string, mixed> $after
     */
    private function audit(
        Request $request,
        string $action,
        ReporteProgramado $schedule,
        array $after
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $this->userId($request),
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $action,
                'tabla_afectada' => 'reportes_programados',
                'registro_clave' => (string) $schedule->id,
                'antes' => null,
                'despues' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'scheduled_report_audit',
                [
                    'action' => $action,
                    'schedule_id' => $schedule->id,
                    'user_id' => $schedule->user_id,
                ]
            );
        }
    }
}
