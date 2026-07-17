<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterValorActivoHistoryRequest;
use App\Services\ValorActivoHistoryService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ValorActivoHistoryController extends Controller
{
    public function __construct(
        private readonly ValorActivoHistoryService $historyService
    ) {
    }

    public function index(
        FilterValorActivoHistoryRequest $request,
        string $numeroActivo
    ) {
        $numeroActivo = mb_strtoupper(trim($numeroActivo), 'UTF-8');
        $activo = $this->findAsset($numeroActivo);

        abort_if(!$activo, 404, 'El activo solicitado no existe en SWAFI.');

        $filters = $request->validated();
        $history = $this->historyService->paginate($numeroActivo, $filters);

        $this->registerQueryAudit($numeroActivo, $filters);

        return view('swafi.valores-historial', [
            'activo' => $activo,
            'valorActual' => $this->findCurrentValue($numeroActivo),
            'historial' => $history,
            'resumen' => $this->historyService->summary($numeroActivo),
            'accionesDisponibles' => $this->historyService->availableActions($numeroActivo),
            'usuariosDisponibles' => $this->historyService->availableUsers($numeroActivo),
            'filtros' => $filters,
        ]);
    }

    private function findAsset(string $numeroActivo): ?object
    {
        return DB::table('activos as a')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->where('a.numero_activo', $numeroActivo)
            ->select([
                'a.numero_activo',
                'a.descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'a.activo',
                'p.nombre as proveedor_nombre',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                'ta.descripcion as tipo_activo',
            ])
            ->first();
    }

    private function findCurrentValue(string $numeroActivo): ?object
    {
        return DB::table('valores_activo as v')
            ->where('v.numero_activo', $numeroActivo)
            ->select([
                'v.id',
                'v.valor_fiscal',
                'v.valor_financiero',
                'v.moneda',
                'v.tipo_cambio',
                'v.depreciacion_acumulada',
                'v.valor_en_libros',
                'v.vida_util_meses',
                'v.estatus_contable',
                'v.conciliacion_cfdi',
                'v.fecha_corte',
                'v.motivo_cambio',
                'v.deleted_at',
                'v.updated_at',
            ])
            ->first();
    }

    private function registerQueryAudit(string $numeroActivo, array $filters): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => auth()->id(),
                'modulo' => 'M02 Control fiscal y financiero',
                'accion' => 'CONSULTA_HIST_VALORES',
                'tabla_afectada' => 'bitacora_auditoria',
                'registro_clave' => $numeroActivo,
                'antes' => null,
                'despues' => json_encode([
                    'filtros' => array_intersect_key($filters, array_flip([
                        'accion',
                        'usuario_id',
                        'fecha_desde',
                        'fecha_hasta',
                        'per_page',
                    ])),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
