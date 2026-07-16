<?php

namespace App\Http\Controllers;

use App\Models\ReporteGuardado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReporteGuardadoController extends Controller
{
    private const REPORT_TYPES = [
        'expedientes_documentales',
        'expedientes_incompletos',
        'activos_sin_documentacion',
        'valores_fiscales',
        'ubicacion_inventario',
        'activos_no_verificados',
        'discrepancias_inventario',
        'actividad_bitacora',
    ];

    private const REPORT_PERMISSIONS = [
        'expedientes_documentales' => 'reportes.documentales',
        'expedientes_incompletos' => 'reportes.documentales',
        'activos_sin_documentacion' => 'reportes.documentales',
        'valores_fiscales' => 'reportes.valores',
        'ubicacion_inventario' => 'reportes.inventario',
        'activos_no_verificados' => 'reportes.inventario',
        'discrepancias_inventario' => 'reportes.inventario',
        'actividad_bitacora' => 'reportes.bitacora',
    ];

    private const FILTER_FIELDS = [
        'tipo_reporte',
        'numero_activo',
        'planta_id',
        'proveedor_id',
        'centro_costo_id',
        'tipo_activo_id',
        'area_id',
        'responsable_id',
        'usuario_id',
        'modulo',
        'estatus_documental',
        'estatus_contable',
        'estatus_operativo',
        'estatus_localizacion',
        'fecha_desde',
        'fecha_hasta',
        'monto_desde',
        'monto_hasta',
        'ordenar_por',
        'direccion',
        'per_page',
        'orientacion',
    ];

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->userId();

        abort_if(!$userId, 403, 'No fue posible identificar al usuario de la sesión.');

        $validated = $request->validate([
            'nombre_reporte_guardado' => [
                'required',
                'string',
                'min:3',
                'max:120',
            ],
            'tipo_reporte' => ['required', Rule::in(self::REPORT_TYPES)],
            'columnas' => ['nullable', 'array', 'max:25'],
            'columnas.*' => ['string', 'max:80'],
            'orientacion' => ['nullable', Rule::in(['horizontal', 'vertical'])],
        ], [
            'nombre_reporte_guardado.required' => 'Captura un nombre para guardar los parámetros del reporte.',
            'nombre_reporte_guardado.min' => 'El nombre del reporte debe tener al menos 3 caracteres.',
            'nombre_reporte_guardado.max' => 'El nombre del reporte no debe superar 120 caracteres.',
            'nombre_reporte_guardado.unique' => 'Ya tienes un reporte guardado con ese nombre.',
            'tipo_reporte.required' => 'Selecciona el tipo de reporte.',
            'tipo_reporte.in' => 'El tipo de reporte seleccionado no es válido.',
            'columnas.max' => 'No puedes guardar más de 25 columnas.',
            'orientacion.in' => 'La orientación seleccionada no es válida.',
        ]);

        $requiredPermission = self::REPORT_PERMISSIONS[$validated['tipo_reporte']] ?? null;

        if (!$requiredPermission || !$this->can($requiredPermission)) {
            abort(403, 'Tu usuario no tiene permiso para guardar parámetros de este tipo de reporte.');
        }

        $filters = [];

        foreach (self::FILTER_FIELDS as $field) {
            if ($request->filled($field)) {
                $filters[$field] = $request->input($field);
            }
        }

        $columns = array_values(array_unique(array_filter(
            (array) $request->input('columnas', []),
            static fn ($column): bool => is_string($column) && trim($column) !== ''
        )));

        $nombre = trim($validated['nombre_reporte_guardado']);
        $savedReport = ReporteGuardado::withTrashed()
            ->where('user_id', $userId)
            ->where('nombre', $nombre)
            ->first();
        $before = $savedReport?->toArray();
        $wasDeleted = $savedReport?->trashed() ?? false;

        if ($savedReport && !$wasDeleted) {
            return back()->withInput()->withErrors([
                'nombre_reporte_guardado' => 'Ya tienes un reporte guardado con ese nombre.',
            ]);
        }

        if ($savedReport) {
            $savedReport->restore();
            $savedReport->forceFill([
                'tipo_reporte' => $validated['tipo_reporte'],
                'filtros' => $filters,
                'columnas' => $columns,
                'orientacion' => $validated['orientacion'] ?? 'horizontal',
                'deleted_by' => null,
                'delete_reason' => null,
            ])->save();
        } else {
            $savedReport = ReporteGuardado::create([
                'user_id' => $userId,
                'nombre' => $nombre,
                'tipo_reporte' => $validated['tipo_reporte'],
                'filtros' => $filters,
                'columnas' => $columns,
                'orientacion' => $validated['orientacion'] ?? 'horizontal',
            ]);
        }

        $this->registerAudit($wasDeleted ? 'REPORTE_GUARDADO_RESTAURADO' : 'REPORTE_GUARDADO_CREADO', [
            'antes' => $before,
            'reporte_guardado_id' => $savedReport->id,
            'nombre' => $savedReport->nombre,
            'tipo_reporte' => $savedReport->tipo_reporte,
            'filtros' => $filters,
            'columnas' => $columns,
        ]);

        return redirect()
            ->route('reportes', $filters)
            ->with('success', $wasDeleted
                ? 'El reporte guardado fue restaurado con los nuevos parámetros.'
                : 'Los parámetros del reporte fueron guardados correctamente.');
    }

    public function apply(int $reporte): RedirectResponse
    {
        $savedReport = ReporteGuardado::query()
            ->where('id', $reporte)
            ->where('user_id', $this->userId())
            ->firstOrFail();

        $requiredPermission = self::REPORT_PERMISSIONS[$savedReport->tipo_reporte] ?? null;

        if (!$requiredPermission || !$this->can($requiredPermission)) {
            abort(403, 'Tu usuario ya no tiene permiso para aplicar este tipo de reporte.');
        }

        $parameters = is_array($savedReport->filtros) ? $savedReport->filtros : [];
        $parameters['tipo_reporte'] = $savedReport->tipo_reporte;
        $parameters['orientacion'] = $savedReport->orientacion ?: 'horizontal';

        if (!empty($savedReport->columnas)) {
            $parameters['columnas'] = array_values($savedReport->columnas);
        }

        $this->registerAudit('REPORTE_GUARDADO_APLICADO', [
            'reporte_guardado_id' => $savedReport->id,
            'nombre' => $savedReport->nombre,
            'tipo_reporte' => $savedReport->tipo_reporte,
        ]);

        $parameters['swafi_focus'] = 'reportes';

        return redirect()->route('reportes', $parameters);
    }

    public function destroy(Request $request, int $reporte): RedirectResponse
    {
        $savedReport = ReporteGuardado::query()
            ->where('id', $reporte)
            ->where('user_id', $this->userId())
            ->firstOrFail();

        $validated = $request->validate([
            'motivo_baja' => ['nullable', 'string', 'max:500'],
        ]);
        $motivoBaja = trim((string) ($validated['motivo_baja'] ?? ''))
            ?: 'Baja lógica solicitada por la persona propietaria del reporte.';
        $snapshot = $savedReport->toArray();

        $savedReport->forceFill([
            'deleted_by' => $this->userId(),
            'delete_reason' => $motivoBaja,
        ])->save();
        $savedReport->delete();

        $this->registerAudit('REPORTE_GUARDADO_BAJA_LOGICA', [
            'reporte_guardado_antes' => $snapshot,
            'reporte_guardado_despues' => ReporteGuardado::withTrashed()->find($reporte)?->toArray(),
        ]);

        return back()->with('success', 'El reporte guardado fue dado de baja lógicamente y se conserva para trazabilidad.');
    }

    private function can(string $permission): bool
    {
        $roles = session('swafi_roles', []);
        $permissions = session('swafi_permissions', []);

        if (in_array('Administrador SWAFI', $roles, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }

    private function registerAudit(string $action, array $details): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $details['filtros']['numero_activo'] ?? null,
                'user_id' => $this->userId(),
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $action,
                'tabla_afectada' => 'reportes_guardados',
                'registro_clave' => isset($details['reporte_guardado_id'])
                    ? (string) $details['reporte_guardado_id']
                    : null,
                'antes' => null,
                'despues' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La gestión del reporte guardado no debe fallar por un error de bitácora.
        }
    }
}
