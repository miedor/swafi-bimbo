<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Expediente;
use App\Models\MovimientoUbicacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InitialAssetLocationService
{
    public function __construct(private readonly InventoryPeriodService $inventoryPeriods)
    {
    }

    /**
     * @return array{ubicaciones: Collection<int, object>, responsables: Collection<int, object>}
     */
    public function optionsForPlant(int $plantId): array
    {
        if ($plantId <= 0) {
            return [
                'ubicaciones' => collect(),
                'responsables' => collect(),
            ];
        }

        $locations = DB::table('ubicaciones as u')
            ->leftJoin('areas as a', 'a.id', '=', 'u.area_id')
            ->where('u.planta_id', $plantId)
            ->where('u.estatus', 'activo')
            ->where(function ($query): void {
                $query->whereNull('u.area_id')
                    ->orWhere('a.estatus', 'activo');
            })
            ->select([
                'u.id',
                'u.planta_id',
                'u.area_id',
                'u.codigo_interno',
                'u.descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',
                'a.nombre as area_nombre',
            ])
            ->orderBy('a.nombre')
            ->orderBy('u.codigo_interno')
            ->orderBy('u.descripcion')
            ->get();

        $responsibles = DB::table('responsables')
            ->where('estatus', 'activo')
            ->select(['id', 'nombre', 'correo'])
            ->orderBy('nombre')
            ->get();

        return [
            'ubicaciones' => $locations,
            'responsables' => $responsibles,
        ];
    }

    public function confirm(
        int $expedienteId,
        array $data,
        int $userId,
        ?string $ipAddress = null
    ): MovimientoUbicacion {
        return DB::transaction(function () use ($expedienteId, $data, $userId, $ipAddress): MovimientoUbicacion {
            $expedient = Expediente::query()
                ->whereKey($expedienteId)
                ->lockForUpdate()
                ->firstOrFail();

            $asset = Activo::query()
                ->where('numero_activo', $expedient->numero_activo)
                ->lockForUpdate()
                ->firstOrFail();

            if (!(bool) $asset->activo) {
                throw ValidationException::withMessages([
                    'ubicacion_id' => 'El activo se encuentra inactivo y no admite una asignación de ubicación.',
                ]);
            }

            if ($asset->ubicacion_id !== null) {
                throw ValidationException::withMessages([
                    'ubicacion_id' => 'El activo ya cuenta con una ubicación actual. Registra un movimiento desde el módulo de Ubicación e inventario.',
                ]);
            }

            $hasMovementHistory = MovimientoUbicacion::query()
                ->where('numero_activo', $asset->numero_activo)
                ->lockForUpdate()
                ->exists();

            if ($hasMovementHistory) {
                throw ValidationException::withMessages([
                    'ubicacion_id' => 'El activo ya tiene historial de movimientos. Para evitar alterar la trazabilidad, utiliza el flujo de movimiento o traslado.',
                ]);
            }

            $assignmentDate = Carbon::parse((string) $data['fecha_asignacion'])->startOfDay();
            $destination = $this->assertInitialMovementAllowed(
                asset: $asset,
                destinationLocationId: (int) $data['ubicacion_id'],
                assignmentDate: $assignmentDate
            );

            if ((int) $destination->planta_id !== (int) $asset->planta_id) {
                throw ValidationException::withMessages([
                    'ubicacion_id' => 'La ubicación inicial debe pertenecer a la misma planta registrada para el activo.',
                ]);
            }

            if (
                $asset->fecha_adquisicion
                && $assignmentDate->lt(Carbon::parse((string) $asset->fecha_adquisicion)->startOfDay())
            ) {
                throw ValidationException::withMessages([
                    'fecha_asignacion' => 'La fecha de asignación inicial no puede ser anterior a la fecha de adquisición del activo.',
                ]);
            }

            $responsibleId = !empty($data['responsable_id'])
                ? (int) $data['responsable_id']
                : null;

            if ($responsibleId !== null) {
                $responsibleExists = DB::table('responsables')
                    ->where('id', $responsibleId)
                    ->where('estatus', 'activo')
                    ->exists();

                if (!$responsibleExists) {
                    throw ValidationException::withMessages([
                        'responsable_id' => 'El responsable seleccionado no existe o se encuentra inactivo.',
                    ]);
                }
            }

            $beforeAsset = $asset->toArray();

            $asset->forceFill([
                'ubicacion_id' => (int) $destination->id,
                'responsable_id' => $responsibleId,
                'actualizado_por' => $userId,
            ])->save();

            $movement = MovimientoUbicacion::create([
                'numero_activo' => $asset->numero_activo,
                'ubicacion_origen_id' => null,
                'ubicacion_destino_id' => (int) $destination->id,
                'motivo' => trim((string) $data['motivo']),
                'evidencia' => $data['evidencia'] ?? null,
                'fecha_movimiento' => $assignmentDate,
                'responsable_id' => $responsibleId,
                'registrado_por' => $userId,
            ]);

            $afterAsset = $asset->fresh()->toArray();

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $asset->numero_activo,
                'user_id' => $userId,
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => 'UBICACION_INICIAL_CONFIRMADA',
                'tabla_afectada' => 'movimientos_ubicacion',
                'registro_clave' => (string) $movement->id,
                'antes' => json_encode([
                    'activo' => $beforeAsset,
                    'expediente_id' => $expedient->id,
                ], JSON_UNESCAPED_UNICODE),
                'despues' => json_encode([
                    'activo' => $afterAsset,
                    'movimiento' => $movement->toArray(),
                    'tipo_asignacion' => 'inicial',
                    'expediente_id' => $expedient->id,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $ipAddress,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $movement;
        }, 3);
    }

    private function assertInitialMovementAllowed(
        Activo $asset,
        int $destinationLocationId,
        Carbon $assignmentDate
    ): object {
        try {
            return $this->inventoryPeriods->assertMovementAllowed(
                asset: $asset,
                destinationLocationId: $destinationLocationId,
                date: $assignmentDate
            );
        } catch (ValidationException $exception) {
            $mappedErrors = [];

            foreach ($exception->errors() as $field => $messages) {
                $mappedField = match ($field) {
                    'ubicacion_destino_id' => 'ubicacion_id',
                    'fecha_movimiento' => 'fecha_asignacion',
                    default => $field,
                };

                $mappedErrors[$mappedField] = array_values((array) $messages);
            }

            throw ValidationException::withMessages($mappedErrors);
        }
    }
}
