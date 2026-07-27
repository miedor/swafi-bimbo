<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferDashboardQueueService
{
    /**
     * @return array{total:int,items:\Illuminate\Support\Collection<int, object>}
     */
    public function pendingForApprover(int $userId, bool $isAdministrator, bool $canApprove): array
    {
        if (!$canApprove || $userId <= 0 || !Schema::hasTable('solicitudes_traslado')) {
            return $this->emptyQueue();
        }

        $query = $this->baseQuery()
            ->where('st.estatus', 'pendiente');

        if (!$isAdministrator) {
            $query->where('st.aprobador_asignado_id', $userId);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderBy('st.solicitado_at')
            ->orderBy('st.id')
            ->limit(8)
            ->get();

        return [
            'total' => $total,
            'items' => $items,
        ];
    }

    /**
     * @return array{total:int,pending:int,items:\Illuminate\Support\Collection<int, object>}
     */
    public function latestForRequester(int $userId, bool $canRequest): array
    {
        if (!$canRequest || $userId <= 0 || !Schema::hasTable('solicitudes_traslado')) {
            return [
                'total' => 0,
                'pending' => 0,
                'items' => collect(),
            ];
        }

        $query = $this->baseQuery()
            ->where('st.solicitado_por', $userId);

        $total = (clone $query)->count();
        $pending = (clone $query)
            ->where('st.estatus', 'pendiente')
            ->count();
        $items = $query
            ->orderByRaw("CASE WHEN st.estatus = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('st.solicitado_at')
            ->orderByDesc('st.id')
            ->limit(8)
            ->get();

        return [
            'total' => $total,
            'pending' => $pending,
            'items' => $items,
        ];
    }

    private function baseQuery()
    {
        $query = DB::table('solicitudes_traslado as st')
            ->join('activos as a', 'a.numero_activo', '=', 'st.numero_activo')
            ->leftJoin('ubicaciones as uo', 'uo.id', '=', 'st.ubicacion_origen_id')
            ->leftJoin('plantas as po', 'po.id', '=', 'uo.planta_id')
            ->join('ubicaciones as ud', 'ud.id', '=', 'st.ubicacion_destino_id')
            ->join('plantas as pd', 'pd.id', '=', 'ud.planta_id')
            ->leftJoin('users as us', 'us.id', '=', 'st.solicitado_por')
            ->leftJoin('users as ua', 'ua.id', '=', 'st.aprobador_asignado_id')
            ->leftJoin('users as ur', 'ur.id', '=', 'st.resuelto_por')
            ->select([
                'st.id',
                'st.uuid',
                'st.numero_activo',
                'a.descripcion as activo_descripcion',
                'st.estatus',
                'st.fecha_movimiento',
                'st.motivo',
                'st.solicitado_por',
                'st.solicitado_at',
                'st.aprobador_asignado_id',
                'st.notificacion_aprobador_at',
                'st.notificacion_aprobador_intentos',
                'st.notificacion_aprobador_error',
                'st.resuelto_por',
                'st.resuelto_at',
                'st.comentario_resolucion',
                'uo.codigo_interno as origen_codigo',
                'uo.descripcion as origen_descripcion',
                'po.nombre as origen_planta',
                'ud.codigo_interno as destino_codigo',
                'ud.descripcion as destino_descripcion',
                'pd.nombre as destino_planta',
                'us.name as solicitado_por_nombre',
                'us.email as solicitado_por_email',
                'ua.name as aprobador_asignado_nombre',
                'ua.email as aprobador_asignado_email',
                'ur.name as resuelto_por_nombre',
            ]);

        if (Schema::hasColumn('solicitudes_traslado', 'notificacion_solicitante_at')) {
            $query->addSelect([
                'st.notificacion_solicitante_at',
                'st.ultimo_intento_notificacion_solicitante_at',
                'st.notificacion_solicitante_intentos',
                'st.notificacion_solicitante_error',
            ]);
        } else {
            $query->addSelect([
                DB::raw('NULL as notificacion_solicitante_at'),
                DB::raw('NULL as ultimo_intento_notificacion_solicitante_at'),
                DB::raw('0 as notificacion_solicitante_intentos'),
                DB::raw('NULL as notificacion_solicitante_error'),
            ]);
        }

        return $query;
    }

    /**
     * @return array{total:int,items:\Illuminate\Support\Collection<int, object>}
     */
    private function emptyQueue(): array
    {
        return [
            'total' => 0,
            'items' => collect(),
        ];
    }
}
