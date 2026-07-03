<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        $tipoReporte = $this->normalizeReportType($request->input('tipo_reporte', 'expedientes_documentales'));

        $query = $this->queryForReport($tipoReporte);
        $this->applyFilters($query, $request, $tipoReporte);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query, $tipoReporte);
        }

        $kpis = $this->buildKpis($query, $tipoReporte);

        $this->applyOrder($query, $tipoReporte);

        $resultados = $query
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('swafi.reportes', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'tipoReporte' => $tipoReporte,
            'tiposReporte' => $this->reportTypes(),
            'columnas' => $this->columnsFor($tipoReporte),
            'kpis' => $kpis,
        ]);
    }

    private function reportTypes(): array
    {
        return [
            'expedientes_documentales' => 'Expedientes documentales',
            'expedientes_incompletos' => 'Expedientes incompletos',
            'valores_fiscales' => 'Valores fiscales y financieros',
            'ubicacion_inventario' => 'Ubicación física e inventario',
        ];
    }

    private function normalizeReportType(?string $type): string
    {
        $type = (string) $type;

        return array_key_exists($type, $this->reportTypes())
            ? $type
            : 'expedientes_documentales';
    }

    private function queryForReport(string $tipoReporte)
    {
        return match ($tipoReporte) {
            'expedientes_incompletos' => $this->queryExpedientesIncompletos(),
            'valores_fiscales' => $this->queryValoresFiscales(),
            'ubicacion_inventario' => $this->queryUbicacionInventario(),
            default => $this->queryExpedientesDocumentales(),
        };
    }

    private function queryExpedientesDocumentales()
    {
        $documentCounts = $this->documentCountsSubquery();

        return DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoinSub($documentCounts, 'dc', function ($join) {
                $join->on('dc.expediente_id', '=', 'e.id');
            })
            ->select([
                'e.id as expediente_id',
                'e.numero_activo',
                'a.descripcion as activo_descripcion',
                'e.folio_factura',
                'e.uuid_cfdi',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                'ta.descripcion as tipo_activo',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                DB::raw("CASE WHEN COALESCE(dc.total_pdf, 0) > 0 THEN 'Sí' ELSE 'No' END as tiene_pdf"),
                DB::raw("CASE WHEN COALESCE(dc.total_xml, 0) > 0 THEN 'Sí' ELSE 'No' END as tiene_xml"),
                'e.estatus as estatus_documental',
                'e.created_at',
            ]);
    }

    private function queryExpedientesIncompletos()
    {
        $query = $this->queryExpedientesDocumentales();

        $query->addSelect(DB::raw("CASE
            WHEN COALESCE(dc.total_pdf, 0) = 0 AND COALESCE(dc.total_xml, 0) = 0 THEN 'PDF y XML'
            WHEN COALESCE(dc.total_pdf, 0) = 0 THEN 'PDF'
            WHEN COALESCE(dc.total_xml, 0) = 0 THEN 'XML'
            WHEN e.estatus <> 'completo' THEN 'Revisión de estatus'
            ELSE 'Sin faltante'
        END as documento_faltante"));

        return $query->where(function ($where) {
            $where->where('e.estatus', '<>', 'completo')
                ->orWhereRaw('COALESCE(dc.total_pdf, 0) = 0')
                ->orWhereRaw('COALESCE(dc.total_xml, 0) = 0');
        });
    }

    private function queryValoresFiscales()
    {
        $latestExpedientes = DB::table('expedientes')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        return DB::table('valores_activo as v')
            ->join('activos as a', 'a.numero_activo', '=', 'v.numero_activo')
            ->leftJoinSub($latestExpedientes, 'le', function ($join) {
                $join->on('le.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->select([
                'v.id as valor_id',
                'v.numero_activo',
                'a.descripcion as activo_descripcion',
                'e.folio_factura',
                'p.nombre as proveedor_nombre',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                'ta.descripcion as tipo_activo',
                'v.valor_fiscal',
                'v.depreciacion_acumulada',
                'v.valor_en_libros',
                'v.valor_financiero',
                'v.vida_util_meses',
                'v.fecha_corte',
                'v.estatus_contable',
                'v.created_at',
            ]);
    }

    private function queryUbicacionInventario()
    {
        $latestInventarios = DB::table('inventarios_activo')
            ->select('numero_activo', DB::raw('MAX(id) as inventario_id'))
            ->groupBy('numero_activo');

        $latestMovimientos = DB::table('movimientos_ubicacion')
            ->select('numero_activo', DB::raw('MAX(id) as movimiento_id'))
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoinSub($latestInventarios, 'li', function ($join) {
                $join->on('li.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('inventarios_activo as ia', 'ia.id', '=', 'li.inventario_id')
            ->leftJoinSub($latestMovimientos, 'lm', function ($join) {
                $join->on('lm.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('movimientos_ubicacion as mu', 'mu.id', '=', 'lm.movimiento_id')
            ->select([
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'pl.nombre as planta_nombre',
                'ar.nombre as area_nombre',
                DB::raw("NULLIF(CONCAT_WS(' / ', u.codigo_interno, u.descripcion, u.edificio, u.piso, u.pasillo), '') as ubicacion_actual"),
                'r.nombre as responsable_nombre',
                'a.estatus_operativo',
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
                'ia.observaciones as inventario_observaciones',
                'mu.fecha_movimiento',
                'mu.motivo as movimiento_motivo',
                'a.created_at',
            ]);
    }

    private function documentCountsSubquery()
    {
        return DB::table('documentos_expediente')
            ->select(
                'expediente_id',
                DB::raw("SUM(CASE WHEN UPPER(tipo_documento) = 'PDF' AND vigente = 1 THEN 1 ELSE 0 END) as total_pdf"),
                DB::raw("SUM(CASE WHEN UPPER(tipo_documento) = 'XML' AND vigente = 1 THEN 1 ELSE 0 END) as total_xml")
            )
            ->groupBy('expediente_id');
    }

    private function applyFilters($query, Request $request, string $tipoReporte): void
    {
        if ($request->filled('numero_activo')) {
            if ($tipoReporte === 'valores_fiscales') {
                $query->where('v.numero_activo', 'like', '%' . $request->numero_activo . '%');
            } else {
                $query->where('a.numero_activo', 'like', '%' . $request->numero_activo . '%');
            }
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', $request->planta_id);
        }

        if ($request->filled('proveedor_id') && $tipoReporte !== 'ubicacion_inventario') {
            $query->where('a.proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('centro_costo_id') && $tipoReporte !== 'ubicacion_inventario') {
            $query->where('a.centro_costo_id', $request->centro_costo_id);
        }

        if ($request->filled('tipo_activo_id') && $tipoReporte !== 'ubicacion_inventario') {
            $query->where('a.tipo_activo_id', $request->tipo_activo_id);
        }

        if ($request->filled('area_id') && $tipoReporte === 'ubicacion_inventario') {
            $query->where('u.area_id', $request->area_id);
        }

        if ($request->filled('responsable_id') && $tipoReporte === 'ubicacion_inventario') {
            $query->where('a.responsable_id', $request->responsable_id);
        }

        if ($request->filled('estatus_documental') && in_array($tipoReporte, ['expedientes_documentales', 'expedientes_incompletos'], true)) {
            $query->where('e.estatus', $request->estatus_documental);
        }

        if ($request->filled('estatus_contable') && $tipoReporte === 'valores_fiscales') {
            $query->where('v.estatus_contable', $request->estatus_contable);
        }

        if ($request->filled('estatus_localizacion') && $tipoReporte === 'ubicacion_inventario') {
            if ($request->estatus_localizacion === 'sin_inventario') {
                $query->whereNull('ia.id');
            } else {
                $query->where('ia.estatus_localizacion', $request->estatus_localizacion);
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate($this->dateColumnFor($tipoReporte), '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate($this->dateColumnFor($tipoReporte), '<=', $request->fecha_hasta);
        }

        if ($request->filled('monto_desde')) {
            $query->where($this->amountColumnFor($tipoReporte), '>=', $request->monto_desde);
        }

        if ($request->filled('monto_hasta')) {
            $query->where($this->amountColumnFor($tipoReporte), '<=', $request->monto_hasta);
        }
    }

    private function dateColumnFor(string $tipoReporte): string
    {
        return match ($tipoReporte) {
            'valores_fiscales' => 'v.fecha_corte',
            'ubicacion_inventario' => 'ia.fecha_inventario',
            default => 'e.fecha_factura',
        };
    }

    private function amountColumnFor(string $tipoReporte): string
    {
        return match ($tipoReporte) {
            'valores_fiscales' => 'v.valor_fiscal',
            default => 'e.monto_factura',
        };
    }

    private function applyOrder($query, string $tipoReporte): void
    {
        match ($tipoReporte) {
            'valores_fiscales' => $query->orderByDesc('v.fecha_corte')->orderByDesc('v.id'),
            'ubicacion_inventario' => $query->orderBy('pl.nombre')->orderBy('a.numero_activo'),
            default => $query->orderByDesc('e.fecha_factura')->orderByDesc('e.id'),
        };
    }

    private function buildKpis($query, string $tipoReporte): array
    {
        $total = (clone $query)->count();

        $sumatoria = null;
        $sumatoriaLabel = 'Monto filtrado';

        if (in_array($tipoReporte, ['expedientes_documentales', 'expedientes_incompletos'], true)) {
            $sumatoria = (clone $query)->sum('e.monto_factura');
            $sumatoriaLabel = 'Monto facturas';
        }

        if ($tipoReporte === 'valores_fiscales') {
            $sumatoria = (clone $query)->sum('v.valor_fiscal');
            $sumatoriaLabel = 'Valor fiscal';
        }

        return [
            'total' => $total,
            'sumatoria' => $sumatoria,
            'sumatoria_label' => $sumatoriaLabel,
            'tipo' => $this->reportTypes()[$tipoReporte] ?? 'Reporte',
            'exportable' => 'CSV',
        ];
    }

    private function columnsFor(string $tipoReporte): array
    {
        return match ($tipoReporte) {
            'expedientes_incompletos' => [
                'numero_activo' => 'Activo',
                'folio_factura' => 'Folio factura',
                'proveedor_nombre' => 'Proveedor',
                'planta_nombre' => 'Planta',
                'tiene_pdf' => 'PDF',
                'tiene_xml' => 'XML',
                'documento_faltante' => 'Faltante',
                'estatus_documental' => 'Estatus',
                'fecha_factura' => 'Fecha factura',
            ],
            'valores_fiscales' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro costo',
                'valor_fiscal' => 'Valor fiscal',
                'depreciacion_acumulada' => 'Depreciación',
                'valor_en_libros' => 'Valor libros',
                'valor_financiero' => 'Valor financiero',
                'fecha_corte' => 'Fecha corte',
                'estatus_contable' => 'Estatus',
            ],
            'ubicacion_inventario' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'planta_nombre' => 'Planta',
                'area_nombre' => 'Área',
                'ubicacion_actual' => 'Ubicación actual',
                'responsable_nombre' => 'Responsable',
                'fecha_inventario' => 'Último inventario',
                'estatus_localizacion' => 'Localización',
                'fecha_movimiento' => 'Último movimiento',
            ],
            default => [
                'numero_activo' => 'Activo',
                'folio_factura' => 'Folio factura',
                'proveedor_nombre' => 'Proveedor',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro costo',
                'fecha_factura' => 'Fecha factura',
                'monto_factura' => 'Monto',
                'moneda' => 'Moneda',
                'tiene_pdf' => 'PDF',
                'tiene_xml' => 'XML',
                'estatus_documental' => 'Estatus',
            ],
        };
    }

    private function exportCsv($query, string $tipoReporte)
    {
        $columns = $this->columnsFor($tipoReporte);
        $this->applyOrder($query, $tipoReporte);
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows, $columns) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns));

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($columns) as $key) {
                    $line[] = data_get($row, $key);
                }

                fputcsv($output, $line);
            }

            fclose($output);
        }, 'reporte_swafi_' . $tipoReporte . '_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function catalogos(): array
    {
        return [
            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'proveedores' => DB::table('proveedores')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'centrosCosto' => DB::table('centros_costo')
                ->where('estatus', 'activo')
                ->orderBy('clave')
                ->get(),

            'tiposActivo' => DB::table('tipos_activo')
                ->where('estatus', 'activo')
                ->orderBy('descripcion')
                ->get(),

            'areas' => DB::table('areas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'responsables' => DB::table('responsables')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),
        ];
    }
}
