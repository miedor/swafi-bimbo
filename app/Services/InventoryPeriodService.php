<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\PeriodoInventario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPeriodService
{
    public function blockingPeriod(int $plantId, Carbon|string $date): ?PeriodoInventario
    {
        $day = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return PeriodoInventario::query()
            ->where('planta_id', $plantId)
            ->where('estatus', 'bloqueado')
            ->whereDate('fecha_inicio', '<=', $day)
            ->whereDate('fecha_fin', '>=', $day)
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    public function assertDateUnlocked(
        int $plantId,
        Carbon|string $date,
        string $field,
        string $operationLabel
    ): void {
        $period = $this->blockingPeriod($plantId, $date);

        if (!$period) {
            return;
        }

        throw ValidationException::withMessages([
            $field => sprintf(
                'No es posible %s porque la planta tiene bloqueado el periodo "%s" del %s al %s. Solicita al Administrador SWAFI que desbloquee el cierre antes de continuar.',
                $operationLabel,
                $period->nombre,
                $period->fecha_inicio->format('d/m/Y'),
                $period->fecha_fin->format('d/m/Y')
            ),
        ]);
    }

    public function assertMovementAllowed(
        Activo $asset,
        int $destinationLocationId,
        Carbon|string $date
    ): object {
        $this->assertAssetAndPlantActive($asset);

        $destination = DB::table('ubicaciones as u')
            ->join('plantas as p', 'p.id', '=', 'u.planta_id')
            ->where('u.id', $destinationLocationId)
            ->where('u.estatus', 'activo')
            ->where('p.estatus', 'activo')
            ->select([
                'u.id',
                'u.planta_id',
                'u.codigo_interno',
                'u.descripcion',
            ])
            ->first();

        if (!$destination) {
            throw ValidationException::withMessages([
                'ubicacion_destino_id' => 'La ubicación de destino no existe o se encuentra inactiva.',
            ]);
        }

        if ((int) $asset->ubicacion_id === (int) $destination->id) {
            throw ValidationException::withMessages([
                'ubicacion_destino_id' => 'La ubicación de destino debe ser diferente de la ubicación actual del activo.',
            ]);
        }

        $this->assertDateUnlocked(
            (int) $asset->planta_id,
            $date,
            'fecha_movimiento',
            'registrar el movimiento de ubicación'
        );

        if ((int) $destination->planta_id !== (int) $asset->planta_id) {
            $this->assertDateUnlocked(
                (int) $destination->planta_id,
                $date,
                'fecha_movimiento',
                'registrar el traslado hacia la planta de destino'
            );
        }

        return $destination;
    }

    public function assertInventoryAllowed(
        Activo $asset,
        ?int $verifiedLocationId,
        Carbon|string $date,
        bool $updateLocation
    ): ?object {
        $this->assertAssetAndPlantActive($asset);

        $this->assertDateUnlocked(
            (int) $asset->planta_id,
            $date,
            'fecha_inventario',
            'registrar la toma de inventario'
        );

        if (!$verifiedLocationId) {
            return null;
        }

        $verifiedLocation = DB::table('ubicaciones as u')
            ->join('plantas as p', 'p.id', '=', 'u.planta_id')
            ->where('u.id', $verifiedLocationId)
            ->where('u.estatus', 'activo')
            ->where('p.estatus', 'activo')
            ->select([
                'u.id',
                'u.planta_id',
                'u.codigo_interno',
                'u.descripcion',
            ])
            ->first();

        if (!$verifiedLocation) {
            throw ValidationException::withMessages([
                'ubicacion_verificada_id' => 'La ubicación verificada no existe o se encuentra inactiva.',
            ]);
        }

        if ((int) $verifiedLocation->planta_id !== (int) $asset->planta_id) {
            $this->assertDateUnlocked(
                (int) $verifiedLocation->planta_id,
                $date,
                'fecha_inventario',
                'registrar la verificación en la planta indicada'
            );

            if ($updateLocation) {
                throw ValidationException::withMessages([
                    'actualizar_ubicacion' => 'La ubicación verificada pertenece a otra planta. Registra una solicitud de traslado para que Contabilidad la apruebe; la toma de inventario puede guardarse sin actualizar la ubicación actual.',
                ]);
            }
        }

        return $verifiedLocation;
    }

    private function assertAssetAndPlantActive(Activo $asset): void
    {
        if (!(bool) $asset->activo) {
            throw ValidationException::withMessages([
                'numero_activo' => 'El activo se encuentra inactivo y no admite movimientos ni tomas de inventario.',
            ]);
        }

        $plantIsActive = DB::table('plantas')
            ->where('id', (int) $asset->planta_id)
            ->where('estatus', 'activo')
            ->exists();

        if (!$plantIsActive) {
            throw ValidationException::withMessages([
                'numero_activo' => 'La planta actual del activo se encuentra inactiva. Corrige el catálogo antes de continuar.',
            ]);
        }
    }
}
