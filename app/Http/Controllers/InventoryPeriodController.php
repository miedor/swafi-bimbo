<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryPeriodRequest;
use App\Http\Requests\UpdateInventoryPeriodStatusRequest;
use App\Models\PeriodoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryPeriodController extends Controller
{
    public function store(StoreInventoryPeriodRequest $request)
    {
        $period = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $overlappingPeriod = PeriodoInventario::query()
                ->where('planta_id', (int) $data['planta_id'])
                ->whereDate('fecha_inicio', '<=', $data['fecha_fin'])
                ->whereDate('fecha_fin', '>=', $data['fecha_inicio'])
                ->lockForUpdate()
                ->first(['id']);

            if ($overlappingPeriod) {
                throw ValidationException::withMessages([
                    'fecha_inicio' => 'Otro usuario registró un periodo que se cruza con las fechas seleccionadas. Actualiza la pantalla e intenta con un rango diferente.',
                ]);
            }

            $period = PeriodoInventario::create([
                'uuid' => (string) Str::uuid(),
                'planta_id' => (int) $data['planta_id'],
                'nombre' => trim((string) $data['nombre']),
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'estatus' => 'abierto',
                'observaciones' => $this->nullableTrim($data['observaciones'] ?? null),
                'creado_por' => $this->userId(),
            ]);

            $this->audit(
                action: 'PERIODO_INVENTARIO_CREADO',
                recordKey: (string) $period->id,
                before: null,
                after: ['periodo' => $period->toArray()]
            );

            return $period;
        });

        return redirect()
            ->route('ubicacion', ['panel' => 'periodos'])
            ->with('success', sprintf(
                'El periodo "%s" fue creado en estado abierto. Puedes bloquearlo cuando inicie la conciliación.',
                $period->nombre
            ));
    }

    public function block(UpdateInventoryPeriodStatusRequest $request, PeriodoInventario $periodo)
    {
        DB::transaction(function () use ($request, $periodo) {
            $period = PeriodoInventario::query()
                ->whereKey($periodo->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->estatus === 'bloqueado') {
                throw ValidationException::withMessages([
                    'motivo_estado' => 'El periodo ya se encuentra bloqueado.',
                ]);
            }

            $before = $period->toArray();

            $period->update([
                'estatus' => 'bloqueado',
                'motivo_bloqueo' => trim((string) $request->validated('motivo_estado')),
                'bloqueado_por' => $this->userId(),
                'bloqueado_at' => now(),
                'desbloqueado_por' => null,
                'desbloqueado_at' => null,
            ]);

            $this->audit(
                action: 'PERIODO_INVENTARIO_BLOQUEADO',
                recordKey: (string) $period->id,
                before: ['periodo' => $before],
                after: ['periodo' => $period->fresh()->toArray()]
            );
        });

        return redirect()
            ->route('ubicacion', ['panel' => 'periodos'])
            ->with('warning', 'El periodo quedó bloqueado. SWAFI impedirá movimientos e inventarios con fechas comprendidas dentro del cierre.');
    }

    public function unblock(UpdateInventoryPeriodStatusRequest $request, PeriodoInventario $periodo)
    {
        DB::transaction(function () use ($request, $periodo) {
            $period = PeriodoInventario::query()
                ->whereKey($periodo->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->estatus !== 'bloqueado') {
                throw ValidationException::withMessages([
                    'motivo_estado' => 'El periodo ya se encuentra abierto.',
                ]);
            }

            $before = $period->toArray();

            $period->update([
                'estatus' => 'abierto',
                'desbloqueado_por' => $this->userId(),
                'desbloqueado_at' => now(),
            ]);

            $this->audit(
                action: 'PERIODO_INVENTARIO_DESBLOQUEADO',
                recordKey: (string) $period->id,
                before: ['periodo' => $before],
                after: [
                    'periodo' => $period->fresh()->toArray(),
                    'motivo_desbloqueo' => trim((string) $request->validated('motivo_estado')),
                ]
            );
        });

        return redirect()
            ->route('ubicacion', ['panel' => 'periodos'])
            ->with('success', 'El periodo fue desbloqueado. Las operaciones fechadas dentro del rango vuelven a estar disponibles.');
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function audit(
        string $action,
        ?string $recordKey,
        ?array $before,
        ?array $after
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => $this->userId(),
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
            'accion' => $action,
            'tabla_afectada' => 'periodos_inventario',
            'registro_clave' => $recordKey,
            'antes' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}
