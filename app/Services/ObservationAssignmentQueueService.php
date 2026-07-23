<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ObservationAssignmentQueueService
{
    /**
     * @return array{total:int,items:Collection<int, object>}
     */
    public function pendingForUser(int $userId, int $limit = 8): array
    {
        if (
            $userId <= 0
            || !Schema::hasTable('expediente_observaciones')
            || !Schema::hasTable('expedientes')
            || !Schema::hasTable('activos')
        ) {
            return [
                'total' => 0,
                'items' => collect(),
            ];
        }

        $query = DB::table('expediente_observaciones as o')
            ->join('expedientes as e', 'e.id', '=', 'o.expediente_id')
            ->join('activos as a', 'a.numero_activo', '=', 'o.numero_activo')
            ->leftJoin('users as uc', 'uc.id', '=', 'o.creado_por')
            ->whereNull('e.deleted_at')
            ->where('o.asignado_a', $userId)
            ->whereIn('o.estatus', ['abierta', 'en_atencion', 'rechazada'])
            ->select([
                'o.id as observacion_id',
                'o.expediente_id',
                'o.numero_activo',
                'o.tipo_observacion',
                'o.prioridad',
                'o.rol_destino',
                'o.estatus',
                'o.descripcion',
                'o.respuesta_atencion',
                'o.fecha_asignacion',
                'o.fecha_compromiso',
                'o.fecha_notificacion',
                'o.notificacion_error',
                'o.created_at',
                'o.updated_at',
                'e.folio_factura',
                'a.descripcion as activo_descripcion',
                'uc.name as creado_por_nombre',
                'uc.email as creado_por_email',
            ]);

        $total = (clone $query)->count();
        $items = $query
            ->orderByRaw("CASE o.prioridad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN o.fecha_compromiso IS NULL THEN 1 ELSE 0 END')
            ->orderBy('o.fecha_compromiso')
            ->orderByDesc('o.updated_at')
            ->limit(max(1, $limit))
            ->get();

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
