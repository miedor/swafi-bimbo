<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusquedaController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $resultados = $query
            ->orderByDesc('e.created_at')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('swafi.busqueda', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
        ]);
    }

    public function show(?int $expediente = null)
    {
        if (!$expediente) {
            $expediente = DB::table('expedientes')->latest('id')->value('id');

            if (!$expediente) {
                return view('swafi.expediente', [
                    'expediente' => null,
                    'documentos' => collect(),
                    'valor' => null,
                    'bitacora' => collect(),
                ]);
            }
        }

        $detalle = DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->where('e.id', $expediente)
            ->select([
                'e.id as expediente_id',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                'e.estatus as expediente_estatus',
                'e.observaciones',
                'e.created_at as expediente_creado',
                'e.updated_at as expediente_actualizado',
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'a.serie',
                'a.marca',
                'a.modelo',
                'a.fecha_adquisicion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'cc.clave as centro_costo_clave',
                'cc.descripcion as centro_costo_descripcion',
                'pl.nombre as planta_nombre',
                'ta.descripcion as tipo_activo',
                'u.codigo_interno as ubicacion_codigo',
                'u.descripcion as ubicacion_descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',
                'ar.nombre as area_nombre',
                'r.nombre as responsable_nombre',
                'r.correo as responsable_correo',
            ])
            ->first();

        abort_if(!$detalle, 404);

        $documentos = DB::table('documentos_expediente')
            ->where('expediente_id', $detalle->expediente_id)
            ->orderBy('tipo_documento')
            ->orderByDesc('version')
            ->get();

        $valor = DB::table('valores_activo')
            ->where('numero_activo', $detalle->numero_activo)
            ->orderByDesc('fecha_corte')
            ->orderByDesc('id')
            ->first();

        $bitacora = DB::table('bitacora_auditoria')
            ->where('numero_activo', $detalle->numero_activo)
            ->orderByDesc('fecha_evento')
            ->limit(10)
            ->get();

        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $detalle->numero_activo,
            'user_id' => auth()->id(),
            'modulo' => 'M03 Consultas',
            'accion' => 'CONSULTA',
            'tabla_afectada' => 'expedientes',
            'registro_clave' => (string) $detalle->expediente_id,
            'antes' => null,
            'despues' => json_encode([
                'folio_factura' => $detalle->folio_factura,
                'numero_activo' => $detalle->numero_activo,
            ], JSON_UNESCAPED_UNICODE),
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return view('swafi.expediente', [
            'expediente' => $detalle,
            'documentos' => $documentos,
            'valor' => $valor,
            'bitacora' => $bitacora,
        ]);
    }

    private function baseQuery()
    {
        return DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->select([
                'e.id as expediente_id',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                'e.estatus',
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'cc.clave as centro_costo_clave',
                'cc.descripcion as centro_costo_descripcion',
                'pl.nombre as planta_nombre',
                'u.descripcion as ubicacion_descripcion',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('folio_factura')) {
            $query->where('e.folio_factura', 'like', '%' . $request->folio_factura . '%');
        }

        if ($request->filled('numero_activo')) {
            $query->where('a.numero_activo', 'like', '%' . $request->numero_activo . '%');
        }

        if ($request->filled('proveedor')) {
            $query->where('p.nombre', 'like', '%' . $request->proveedor . '%');
        }

        if ($request->filled('rfc')) {
            $query->where('p.rfc', 'like', '%' . $request->rfc . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', $request->planta_id);
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('a.centro_costo_id', $request->centro_costo_id);
        }

        if ($request->filled('estatus')) {
            $query->where('e.estatus', $request->estatus);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('e.fecha_factura', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('e.fecha_factura', '<=', $request->fecha_hasta);
        }

        if ($request->filled('monto_desde')) {
            $query->where('e.monto_factura', '>=', $request->monto_desde);
        }

        if ($request->filled('monto_hasta')) {
            $query->where('e.monto_factura', '<=', $request->monto_hasta);
        }
    }

    private function catalogos(): array
    {
        return [
            'plantas' => DB::table('plantas')->where('estatus', 'activo')->orderBy('nombre')->get(),
            'centrosCosto' => DB::table('centros_costo')->where('estatus', 'activo')->orderBy('clave')->get(),
        ];
    }

    private function exportCsv($query)
    {
        $rows = $query->orderByDesc('e.created_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'Folio factura',
                'UUID CFDI',
                'Número activo',
                'Proveedor',
                'RFC',
                'Planta',
                'Centro costo',
                'Fecha factura',
                'Monto',
                'Moneda',
                'Estatus',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->folio_factura,
                    $row->uuid_cfdi,
                    $row->numero_activo,
                    $row->proveedor_nombre,
                    $row->proveedor_rfc,
                    $row->planta_nombre,
                    $row->centro_costo_clave,
                    $row->fecha_factura,
                    $row->monto_factura,
                    $row->moneda,
                    $row->estatus,
                ]);
            }

            fclose($output);
        }, 'consulta_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
