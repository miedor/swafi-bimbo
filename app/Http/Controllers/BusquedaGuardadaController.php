<?php

namespace App\Http\Controllers;

use App\Models\BusquedaGuardada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusquedaGuardadaController extends Controller
{
    private const MODULO = 'busqueda';

    private const CAMPOS_PERMITIDOS = [
        'folio_factura',
        'uuid_cfdi',
        'proveedor',
        'rfc',
        'numero_activo',
        'planta_id',
        'centro_costo_id',
        'area_id',
        'ubicacion_id',
        'estatus',
        'estatus_operativo',
        'fecha_desde',
        'fecha_hasta',
        'monto_desde',
        'monto_hasta',
        'ordenar_por',
        'direccion',
        'per_page',
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],
            'filtros' => ['required', 'array'],
            'filtros.estatus' => ['nullable', Rule::in(['completo', 'incompleto', 'observado'])],
            'filtros.estatus_operativo' => ['nullable', Rule::in(['en_operacion', 'baja', 'traslado'])],
            'filtros.direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'filtros.per_page' => ['nullable', Rule::in(['10', '25', '50', '100', 10, 25, 50, 100])],
        ], [
            'nombre.required' => 'Debes asignar un nombre a la búsqueda.',
            'nombre.min' => 'El nombre de la búsqueda debe contener al menos 3 caracteres.',
            'nombre.max' => 'El nombre de la búsqueda no debe superar 100 caracteres.',
            'filtros.required' => 'No se recibieron filtros para guardar.',
            'filtros.array' => 'Los filtros recibidos no tienen un formato válido.',
        ]);

        $userId = $this->userId();
        $filtros = $this->normalizeFilters($validated['filtros']);

        if ($this->hasNoMeaningfulFilters($filtros)) {
            return back()->withErrors([
                'busqueda_guardada' => 'Configura al menos un criterio antes de guardar la búsqueda.',
            ]);
        }

        $before = BusquedaGuardada::query()
            ->where('user_id', $userId)
            ->where('modulo', self::MODULO)
            ->where('nombre', trim($validated['nombre']))
            ->first();

        $busqueda = BusquedaGuardada::updateOrCreate(
            [
                'user_id' => $userId,
                'modulo' => self::MODULO,
                'nombre' => trim($validated['nombre']),
            ],
            [
                'filtros' => $filtros,
            ]
        );

        $this->registrarBitacora(
            accion: $before ? 'BUSQUEDA_GUARDADA_ACTUALIZADA' : 'BUSQUEDA_GUARDADA_CREADA',
            registroClave: (string) $busqueda->id,
            antes: $before?->toArray(),
            despues: $busqueda->fresh()->toArray()
        );

        return redirect()
            ->route('busqueda', $filtros)
            ->with('success', $before
                ? 'La búsqueda guardada fue actualizada correctamente.'
                : 'La búsqueda fue guardada correctamente.');
    }

    public function apply(int $busqueda): RedirectResponse
    {
        $busquedaData = $this->findOwnedSearch($busqueda);
        $filtros = $this->normalizeFilters((array) $busquedaData->filtros);

        $this->registrarBitacora(
            accion: 'BUSQUEDA_GUARDADA_APLICADA',
            registroClave: (string) $busquedaData->id,
            antes: null,
            despues: [
                'nombre' => $busquedaData->nombre,
                'filtros' => $filtros,
            ]
        );

        return redirect()->route('busqueda', $filtros);
    }

    public function destroy(int $busqueda): RedirectResponse
    {
        $busquedaData = $this->findOwnedSearch($busqueda);
        $before = $busquedaData->toArray();
        $busquedaData->delete();

        $this->registrarBitacora(
            accion: 'BUSQUEDA_GUARDADA_ELIMINADA',
            registroClave: (string) $busqueda,
            antes: $before,
            despues: null
        );

        return redirect()
            ->route('busqueda')
            ->with('success', 'La búsqueda guardada fue eliminada correctamente.');
    }

    private function findOwnedSearch(int $busqueda): BusquedaGuardada
    {
        return BusquedaGuardada::query()
            ->where('id', $busqueda)
            ->where('user_id', $this->userId())
            ->where('modulo', self::MODULO)
            ->firstOrFail();
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach (self::CAMPOS_PERMITIDOS as $field) {
            if (!array_key_exists($field, $filters)) {
                continue;
            }

            $value = $filters[$field];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            if (in_array($field, ['planta_id', 'centro_costo_id', 'area_id', 'ubicacion_id'], true)) {
                $value = (int) $value;

                if ($value <= 0) {
                    continue;
                }
            }

            if (in_array($field, ['monto_desde', 'monto_hasta'], true)) {
                if (!is_numeric($value)) {
                    continue;
                }

                $value = (float) $value;
            }

            if ($field === 'per_page') {
                $value = (int) $value;

                if (!in_array($value, [10, 25, 50, 100], true)) {
                    $value = 10;
                }
            }

            $normalized[$field] = $value;
        }

        if (!isset($normalized['ordenar_por'])) {
            $normalized['ordenar_por'] = 'fecha_factura';
        }

        if (!isset($normalized['direccion'])) {
            $normalized['direccion'] = 'desc';
        }

        if (!isset($normalized['per_page'])) {
            $normalized['per_page'] = 10;
        }

        return $normalized;
    }

    private function hasNoMeaningfulFilters(array $filters): bool
    {
        $ignored = ['ordenar_por', 'direccion', 'per_page'];

        foreach ($filters as $key => $value) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            if ($value !== '' && $value !== null) {
                return false;
            }
        }

        return true;
    }

    private function userId(): int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());
        abort_if($userId <= 0, 403, 'No fue posible identificar al usuario autenticado.');

        return $userId;
    }

    private function registrarBitacora(
        string $accion,
        ?string $registroClave,
        mixed $antes,
        mixed $despues
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $this->userId(),
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $accion,
                'tabla_afectada' => 'busquedas_guardadas',
                'registro_clave' => $registroClave,
                'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Una falla de bitácora no debe impedir el uso de búsquedas guardadas.
        }
    }
}
