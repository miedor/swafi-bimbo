<?php

namespace App\Http\Controllers;

use App\Models\ReporteGuardado;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportesController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    public function index(
        Request $request,
        SimpleXlsxExporter $xlsxExporter,
        SimplePdfTableExporter $pdfExporter
    ): Response|BinaryFileResponse {
        $availableReportTypes = $this->availableReportTypes();

        abort_if(empty($availableReportTypes), 403, 'Tu usuario no tiene reportes autorizados.');

        $tipoReporte = $this->normalizeReportType(
            $request->input('tipo_reporte'),
            array_keys($availableReportTypes)
        );

        $this->normalizeInventoryVerificationPeriod($request, $tipoReporte);

        $query = $this->queryForReport($tipoReporte, $request);
        $this->applyFilters($query, $request, $tipoReporte);

        $availableColumns = $this->columnsFor($tipoReporte);
        $selectedColumns = $this->selectedColumns($request, $availableColumns);
        $exportFormat = strtolower((string) $request->input('export'));

        if (in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            $this->applyOrder($query, $request, $tipoReporte);

            return $this->exportReport(
                query: $query,
                request: $request,
                tipoReporte: $tipoReporte,
                columns: $selectedColumns,
                format: $exportFormat,
                xlsxExporter: $xlsxExporter,
                pdfExporter: $pdfExporter
            );
        }

        $kpis = $this->buildKpis($query, $tipoReporte);
        $this->applyOrder($query, $request, $tipoReporte);

        $perPage = (int) $request->input('per_page', 10);

        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $resultados = $query
            ->paginate($perPage)
            ->withQueryString();

        return response()->view('swafi.reportes', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'tipoReporte' => $tipoReporte,
            'tiposReporte' => $availableReportTypes,
            'columnasDisponibles' => $availableColumns,
            'columnasSeleccionadas' => $selectedColumns,
            'kpis' => $kpis,
            'reportesGuardados' => $this->savedReports(),
            'canSaveReports' => $this->can('reportes.plantillas'),
            'canExportExcel' => $this->can('reportes.exportar_excel'),
            'canExportPdf' => $this->can('reportes.exportar_pdf'),
            'exportLimit' => self::EXPORT_LIMIT,
        ]);
    }

    private function reportDefinitions(): array
    {
        return [
            'expedientes_documentales' => [
                'label' => 'Expedientes documentales',
                'permission' => 'reportes.documentales',
            ],
            'expedientes_incompletos' => [
                'label' => 'Expedientes incompletos u observados',
                'permission' => 'reportes.documentales',
            ],
            'activos_sin_documentacion' => [
                'label' => 'Activos sin documentación o valores válidos',
                'permission' => 'reportes.documentales',
            ],
            'valores_fiscales' => [
                'label' => 'Valores fiscales y financieros',
                'permission' => 'reportes.valores',
            ],
            'ubicacion_inventario' => [
                'label' => 'Ubicación física e inventario',
                'permission' => 'reportes.inventario',
            ],
            'activos_no_verificados' => [
                'label' => 'Activos no verificados en el periodo',
                'permission' => 'reportes.inventario',
            ],
            'discrepancias_inventario' => [
                'label' => 'Discrepancias de inventario',
                'permission' => 'reportes.inventario',
            ],
            'actividad_bitacora' => [
                'label' => 'Actividad y bitácora',
                'permission' => 'reportes.bitacora',
            ],
        ];
    }

    private function availableReportTypes(): array
    {
        $available = [];

        foreach ($this->reportDefinitions() as $key => $definition) {
            if ($this->can($definition['permission'])) {
                $available[$key] = $definition['label'];
            }
        }

        return $available;
    }

    private function normalizeReportType(?string $type, array $allowedTypes): string
    {
        $type = (string) $type;

        if ($type !== '' && in_array($type, $allowedTypes, true)) {
            return $type;
        }

        return $allowedTypes[0] ?? 'expedientes_documentales';
    }

    private function normalizeInventoryVerificationPeriod(Request $request, string $tipoReporte): void
    {
        if ($tipoReporte !== 'activos_no_verificados') {
            return;
        }

        $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ], [
            'fecha_desde.date' => 'La fecha inicial del periodo de verificación no es válida.',
            'fecha_hasta.date' => 'La fecha final del periodo de verificación no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);

        $request->merge([
            'fecha_desde' => $request->filled('fecha_desde')
                ? (string) $request->input('fecha_desde')
                : now()->startOfYear()->toDateString(),
            'fecha_hasta' => $request->filled('fecha_hasta')
                ? (string) $request->input('fecha_hasta')
                : now()->toDateString(),
        ]);
    }

    private function queryForReport(string $tipoReporte, Request $request): Builder
    {
        return match ($tipoReporte) {
            'expedientes_incompletos' => $this->queryExpedientesIncompletos(),
            'activos_sin_documentacion' => $this->queryActivosSinDocumentacion(),
            'valores_fiscales' => $this->queryValoresFiscales(),
            'ubicacion_inventario' => $this->queryUbicacionInventario(),
            'activos_no_verificados' => $this->queryActivosNoVerificados($request),
            'discrepancias_inventario' => $this->queryDiscrepanciasInventario(),
            'actividad_bitacora' => $this->queryActividadBitacora(),
            default => $this->queryExpedientesDocumentales(),
        };
    }

    private function queryExpedientesDocumentales(): Builder
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
            ->whereNull('e.deleted_at')
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
                'a.estatus_operativo',
                'e.created_at',
            ]);
    }

    private function queryExpedientesIncompletos(): Builder
    {
        $query = $this->queryExpedientesDocumentales();

        $query->addSelect(DB::raw("CASE
            WHEN COALESCE(dc.total_pdf, 0) = 0 AND COALESCE(dc.total_xml, 0) = 0 THEN 'Falta PDF y XML'
            WHEN COALESCE(dc.total_pdf, 0) = 0 THEN 'Falta PDF'
            WHEN COALESCE(dc.total_xml, 0) = 0 THEN 'Falta XML'
            WHEN e.estatus = 'observado' THEN 'Expediente observado'
            WHEN e.estatus <> 'completo' THEN 'Revisión documental'
            ELSE 'Sin faltante'
        END as documento_faltante"));

        return $query->where(function ($where) {
            $where->where('e.estatus', '<>', 'completo')
                ->orWhereRaw('COALESCE(dc.total_pdf, 0) = 0')
                ->orWhereRaw('COALESCE(dc.total_xml, 0) = 0');
        });
    }

    private function queryActivosSinDocumentacion(): Builder
    {
        $latestExpedientes = DB::table('expedientes')
            ->whereNull('deleted_at')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        $documentCounts = $this->documentCountsSubquery();

        $validValues = DB::table('valores_activo')
            ->whereNull('deleted_at')
            ->select(
                'numero_activo',
                DB::raw("MAX(CASE
                    WHEN estatus_contable = 'baja' THEN 1
                    WHEN estatus_contable IN ('vigente', 'en_revision')
                         AND COALESCE(valor_fiscal, 0) > 0
                         AND COALESCE(valor_financiero, 0) > 0 THEN 1
                    ELSE 0
                END) as valores_validos")
            )
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoinSub($latestExpedientes, 'le', function ($join) {
                $join->on('le.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoinSub($documentCounts, 'dc', function ($join) {
                $join->on('dc.expediente_id', '=', 'e.id');
            })
            ->leftJoinSub($validValues, 'vv', function ($join) {
                $join->on('vv.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->where(function ($where) {
                $where->whereNull('e.id')
                    ->orWhereRaw('COALESCE(dc.total_pdf, 0) = 0')
                    ->orWhereRaw('COALESCE(dc.total_xml, 0) = 0')
                    ->orWhereRaw('COALESCE(vv.valores_validos, 0) = 0');
            })
            ->select([
                'e.id as expediente_id',
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'e.folio_factura',
                'p.nombre as proveedor_nombre',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                'ta.descripcion as tipo_activo',
                'e.fecha_factura',
                DB::raw("CASE WHEN COALESCE(dc.total_pdf, 0) > 0 THEN 'Sí' ELSE 'No' END as tiene_pdf"),
                DB::raw("CASE WHEN COALESCE(dc.total_xml, 0) > 0 THEN 'Sí' ELSE 'No' END as tiene_xml"),
                DB::raw("CASE WHEN COALESCE(vv.valores_validos, 0) > 0 THEN 'Sí' ELSE 'No' END as tiene_valores_validos"),
                DB::raw("TRIM(BOTH ', ' FROM CONCAT(
                    CASE WHEN e.id IS NULL THEN 'Sin expediente, ' ELSE '' END,
                    CASE WHEN COALESCE(dc.total_pdf, 0) = 0 THEN 'Sin PDF, ' ELSE '' END,
                    CASE WHEN COALESCE(dc.total_xml, 0) = 0 THEN 'Sin XML, ' ELSE '' END,
                    CASE WHEN COALESCE(vv.valores_validos, 0) = 0 THEN 'Sin valores válidos, ' ELSE '' END
                )) as motivo_pendiente"),
                'e.estatus as estatus_documental',
                'a.estatus_operativo',
                'a.created_at',
            ]);
    }

    private function queryValoresFiscales(): Builder
    {
        $latestExpedientes = DB::table('expedientes')
            ->whereNull('deleted_at')
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
            ->whereNull('v.deleted_at')
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
                'a.estatus_operativo',
                'v.created_at',
            ]);
    }

    private function queryUbicacionInventario(): Builder
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

    private function queryActivosNoVerificados(Request $request): Builder
    {
        $fechaDesde = (string) $request->input('fecha_desde');
        $fechaHasta = (string) $request->input('fecha_hasta');

        $latestInventarios = DB::table('inventarios_activo')
            ->whereDate('fecha_inventario', '<=', $fechaHasta)
            ->select([
                'id',
                'numero_activo',
                'fecha_inventario',
                'estatus_localizacion',
                'ubicacion_verificada_id',
                'verificado_por',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY numero_activo ORDER BY fecha_inventario DESC, id DESC) as fila'),
            ]);

        return DB::table('activos as a')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoinSub($latestInventarios, 'ia', function ($join) {
                $join
                    ->on('ia.numero_activo', '=', 'a.numero_activo')
                    ->where('ia.fila', 1);
            })
            ->leftJoin('ubicaciones as uv', 'uv.id', '=', 'ia.ubicacion_verificada_id')
            ->leftJoin('users as usuario_verificador', 'usuario_verificador.id', '=', 'ia.verificado_por')
            ->where('a.activo', true)
            ->whereNotExists(function ($subquery) use ($fechaDesde, $fechaHasta): void {
                $subquery
                    ->selectRaw('1')
                    ->from('inventarios_activo as inventario_periodo')
                    ->whereColumn('inventario_periodo.numero_activo', 'a.numero_activo')
                    ->whereDate('inventario_periodo.fecha_inventario', '>=', $fechaDesde)
                    ->whereDate('inventario_periodo.fecha_inventario', '<=', $fechaHasta);
            })
            ->select([
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'pl.nombre as planta_nombre',
                'ar.nombre as area_nombre',
                DB::raw("NULLIF(CONCAT_WS(' / ', u.codigo_interno, u.descripcion, u.edificio, u.piso, u.pasillo), '') as ubicacion_actual"),
                'r.nombre as responsable_nombre',
                'a.estatus_operativo',
                'ia.fecha_inventario as ultima_fecha_inventario',
                'ia.estatus_localizacion as ultimo_estatus_localizacion',
                DB::raw("NULLIF(CONCAT_WS(' / ', uv.codigo_interno, uv.descripcion), '') as ultima_ubicacion_verificada"),
                'usuario_verificador.name as ultimo_verificado_por',
                DB::raw("CASE
                    WHEN ia.fecha_inventario IS NULL THEN NULL
                    ELSE DATEDIFF('{$fechaHasta}', ia.fecha_inventario)
                END as dias_desde_ultimo_inventario"),
                DB::raw("CASE
                    WHEN ia.id IS NULL THEN 'Sin inventario histórico'
                    WHEN ia.estatus_localizacion = 'localizado' THEN 'Último inventario localizado antes del periodo seleccionado'
                    WHEN ia.estatus_localizacion = 'no_encontrado' THEN 'Último inventario: activo no encontrado'
                    WHEN ia.estatus_localizacion = 'diferencia' THEN 'Último inventario: diferencia de ubicación'
                    WHEN ia.estatus_localizacion = 'pendiente' THEN 'Último inventario pendiente de resolución'
                    ELSE 'Sin inventario dentro del periodo seleccionado'
                END as motivo_no_verificacion"),
                'a.created_at',
            ]);
    }

    private function queryDiscrepanciasInventario(): Builder
    {
        return DB::table('inventarios_activo as ia')
            ->join('activos as a', 'a.numero_activo', '=', 'ia.numero_activo')
            ->leftJoin('ubicaciones as uactual', 'uactual.id', '=', 'a.ubicacion_id')
            ->leftJoin('ubicaciones as uverificada', 'uverificada.id', '=', 'ia.ubicacion_verificada_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'uverificada.area_id')
            ->leftJoin('users as uv', 'uv.id', '=', 'ia.verificado_por')
            ->whereIn('ia.estatus_localizacion', ['no_encontrado', 'diferencia', 'pendiente'])
            ->select([
                'ia.id as inventario_id',
                'ia.numero_activo',
                'a.descripcion as activo_descripcion',
                'pl.nombre as planta_nombre',
                'ar.nombre as area_nombre',
                DB::raw("NULLIF(CONCAT_WS(' / ', uactual.codigo_interno, uactual.descripcion), '') as ubicacion_registrada"),
                DB::raw("NULLIF(CONCAT_WS(' / ', uverificada.codigo_interno, uverificada.descripcion), '') as ubicacion_verificada"),
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
                'ia.observaciones as inventario_observaciones',
                'uv.name as verificado_por_nombre',
                'a.estatus_operativo',
                'ia.created_at',
            ]);
    }

    private function queryActividadBitacora(): Builder
    {
        return DB::table('bitacora_auditoria as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->select([
                'b.id as bitacora_id',
                'b.fecha_evento',
                'u.name as usuario_nombre',
                'u.email as usuario_email',
                'b.numero_activo',
                'b.modulo',
                'b.accion',
                'b.tabla_afectada',
                'b.registro_clave',
                'b.ip',
                'b.created_at',
            ]);
    }

    private function documentCountsSubquery(): Builder
    {
        return DB::table('documentos_expediente')
            ->select(
                'expediente_id',
                DB::raw("SUM(CASE WHEN UPPER(tipo_documento) = 'PDF' AND vigente = 1 THEN 1 ELSE 0 END) as total_pdf"),
                DB::raw("SUM(CASE WHEN UPPER(tipo_documento) = 'XML' AND vigente = 1 THEN 1 ELSE 0 END) as total_xml")
            )
            ->groupBy('expediente_id');
    }

    private function applyFilters(Builder $query, Request $request, string $tipoReporte): void
    {
        if ($tipoReporte === 'actividad_bitacora') {
            if ($request->filled('numero_activo')) {
                $query->where('b.numero_activo', 'like', '%' . trim((string) $request->numero_activo) . '%');
            }

            if ($request->filled('usuario_id')) {
                $query->where('b.user_id', (int) $request->usuario_id);
            }

            if ($request->filled('modulo')) {
                $query->where('b.modulo', 'like', '%' . trim((string) $request->modulo) . '%');
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('b.fecha_evento', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('b.fecha_evento', '<=', $request->fecha_hasta);
            }

            return;
        }

        if ($request->filled('numero_activo')) {
            $query->where('a.numero_activo', 'like', '%' . trim((string) $request->numero_activo) . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', (int) $request->planta_id);
        }

        if ($request->filled('proveedor_id') && $this->supportsSupplier($tipoReporte)) {
            $query->where('a.proveedor_id', (int) $request->proveedor_id);
        }

        if ($request->filled('centro_costo_id') && $this->supportsSupplier($tipoReporte)) {
            $query->where('a.centro_costo_id', (int) $request->centro_costo_id);
        }

        if ($request->filled('tipo_activo_id') && $this->supportsSupplier($tipoReporte)) {
            $query->where('a.tipo_activo_id', (int) $request->tipo_activo_id);
        }

        if ($request->filled('area_id') && in_array($tipoReporte, [
            'ubicacion_inventario',
            'activos_no_verificados',
            'discrepancias_inventario',
        ], true)) {
            $areaColumn = $tipoReporte === 'discrepancias_inventario'
                ? 'uverificada.area_id'
                : 'u.area_id';

            $query->where($areaColumn, (int) $request->area_id);
        }

        if ($request->filled('responsable_id') && in_array($tipoReporte, [
            'ubicacion_inventario',
            'activos_no_verificados',
        ], true)) {
            $query->where('a.responsable_id', (int) $request->responsable_id);
        }

        if ($request->filled('estatus_documental') && in_array($tipoReporte, [
            'expedientes_documentales',
            'expedientes_incompletos',
            'activos_sin_documentacion',
        ], true)) {
            $query->where('e.estatus', $request->estatus_documental);
        }

        if ($request->filled('estatus_contable') && $tipoReporte === 'valores_fiscales') {
            $query->where('v.estatus_contable', $request->estatus_contable);
        }

        if ($request->filled('estatus_operativo')) {
            $query->where('a.estatus_operativo', $request->estatus_operativo);
        }

        if ($request->filled('estatus_localizacion') && in_array($tipoReporte, [
            'ubicacion_inventario',
            'activos_no_verificados',
            'discrepancias_inventario',
        ], true)) {
            if (
                $request->estatus_localizacion === 'sin_inventario'
                && in_array($tipoReporte, ['ubicacion_inventario', 'activos_no_verificados'], true)
            ) {
                $query->whereNull('ia.id');
            } else {
                $query->where('ia.estatus_localizacion', $request->estatus_localizacion);
            }
        }

        if ($tipoReporte !== 'activos_no_verificados') {
            if ($request->filled('fecha_desde')) {
                $query->whereDate($this->dateColumnFor($tipoReporte), '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate($this->dateColumnFor($tipoReporte), '<=', $request->fecha_hasta);
            }
        }

        $amountColumn = $this->amountColumnFor($tipoReporte);

        if ($amountColumn && $request->filled('monto_desde')) {
            $query->where($amountColumn, '>=', (float) $request->monto_desde);
        }

        if ($amountColumn && $request->filled('monto_hasta')) {
            $query->where($amountColumn, '<=', (float) $request->monto_hasta);
        }
    }

    private function supportsSupplier(string $tipoReporte): bool
    {
        return in_array($tipoReporte, [
            'expedientes_documentales',
            'expedientes_incompletos',
            'activos_sin_documentacion',
            'valores_fiscales',
        ], true);
    }

    private function dateColumnFor(string $tipoReporte): string
    {
        return match ($tipoReporte) {
            'valores_fiscales' => 'v.fecha_corte',
            'ubicacion_inventario', 'activos_no_verificados', 'discrepancias_inventario' => 'ia.fecha_inventario',
            'actividad_bitacora' => 'b.fecha_evento',
            'activos_sin_documentacion' => 'a.created_at',
            default => 'e.fecha_factura',
        };
    }

    private function amountColumnFor(string $tipoReporte): ?string
    {
        return match ($tipoReporte) {
            'valores_fiscales' => 'v.valor_fiscal',
            'expedientes_documentales', 'expedientes_incompletos' => 'e.monto_factura',
            default => null,
        };
    }

    private function applyOrder(Builder $query, Request $request, string $tipoReporte): void
    {
        $allowed = $this->orderColumnsFor($tipoReporte);
        $requested = (string) $request->input('ordenar_por', '');
        $direction = strtolower((string) $request->input('direccion', 'desc'));

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        if ($requested !== '' && isset($allowed[$requested])) {
            $query->orderBy($allowed[$requested], $direction);
        } else {
            match ($tipoReporte) {
                'valores_fiscales' => $query->orderByDesc('v.fecha_corte')->orderByDesc('v.id'),
                'ubicacion_inventario' => $query->orderBy('pl.nombre')->orderBy('a.numero_activo'),
                'activos_no_verificados' => $query->orderBy('pl.nombre')->orderBy('a.numero_activo'),
                'discrepancias_inventario' => $query->orderByDesc('ia.fecha_inventario')->orderByDesc('ia.id'),
                'actividad_bitacora' => $query->orderByDesc('b.fecha_evento')->orderByDesc('b.id'),
                'activos_sin_documentacion' => $query->orderBy('a.numero_activo'),
                default => $query->orderByDesc('e.fecha_factura')->orderByDesc('e.id'),
            };
        }
    }

    private function orderColumnsFor(string $tipoReporte): array
    {
        return match ($tipoReporte) {
            'valores_fiscales' => [
                'numero_activo' => 'v.numero_activo',
                'fecha' => 'v.fecha_corte',
                'monto' => 'v.valor_fiscal',
                'planta' => 'pl.nombre',
            ],
            'ubicacion_inventario' => [
                'numero_activo' => 'a.numero_activo',
                'fecha' => 'ia.fecha_inventario',
                'planta' => 'pl.nombre',
                'estatus' => 'ia.estatus_localizacion',
            ],
            'activos_no_verificados' => [
                'numero_activo' => 'a.numero_activo',
                'fecha' => 'ia.fecha_inventario',
                'planta' => 'pl.nombre',
                'estatus' => 'ia.estatus_localizacion',
            ],
            'discrepancias_inventario' => [
                'numero_activo' => 'ia.numero_activo',
                'fecha' => 'ia.fecha_inventario',
                'planta' => 'pl.nombre',
                'estatus' => 'ia.estatus_localizacion',
            ],
            'actividad_bitacora' => [
                'fecha' => 'b.fecha_evento',
                'usuario' => 'u.name',
                'modulo' => 'b.modulo',
                'accion' => 'b.accion',
            ],
            'activos_sin_documentacion' => [
                'numero_activo' => 'a.numero_activo',
                'fecha' => 'a.created_at',
                'planta' => 'pl.nombre',
                'estatus' => 'e.estatus',
            ],
            default => [
                'numero_activo' => 'e.numero_activo',
                'fecha' => 'e.fecha_factura',
                'monto' => 'e.monto_factura',
                'planta' => 'pl.nombre',
                'estatus' => 'e.estatus',
            ],
        };
    }

    private function buildKpis(Builder $query, string $tipoReporte): array
    {
        $total = (clone $query)->count();
        $sumatoria = null;
        $sumatoriaLabel = 'Sin sumatoria monetaria';

        if (in_array($tipoReporte, ['expedientes_documentales', 'expedientes_incompletos'], true)) {
            $sumatoria = (clone $query)->sum('e.monto_factura');
            $sumatoriaLabel = 'Monto de facturas';
        }

        if ($tipoReporte === 'valores_fiscales') {
            $sumatoria = (clone $query)->sum('v.valor_fiscal');
            $sumatoriaLabel = 'Valor fiscal';
        }

        return [
            'total' => $total,
            'sumatoria' => $sumatoria,
            'sumatoria_label' => $sumatoriaLabel,
            'tipo' => $this->reportDefinitions()[$tipoReporte]['label'] ?? 'Reporte',
            'exportable' => 'CSV · Excel · PDF',
            'limite_exportacion' => self::EXPORT_LIMIT,
        ];
    }

    private function columnsFor(string $tipoReporte): array
    {
        return match ($tipoReporte) {
            'expedientes_incompletos' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'folio_factura' => 'Folio factura',
                'proveedor_nombre' => 'Proveedor',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro de costo',
                'tiene_pdf' => 'PDF',
                'tiene_xml' => 'XML',
                'documento_faltante' => 'Corrección requerida',
                'estatus_documental' => 'Estatus documental',
                'fecha_factura' => 'Fecha factura',
            ],
            'activos_sin_documentacion' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'folio_factura' => 'Folio factura',
                'proveedor_nombre' => 'Proveedor',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro de costo',
                'tipo_activo' => 'Tipo de activo',
                'tiene_pdf' => 'PDF',
                'tiene_xml' => 'XML',
                'tiene_valores_validos' => 'Valores válidos',
                'motivo_pendiente' => 'Pendiente detectado',
                'estatus_operativo' => 'Estatus operativo',
            ],
            'valores_fiscales' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'folio_factura' => 'Folio factura',
                'proveedor_nombre' => 'Proveedor',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro de costo',
                'tipo_activo' => 'Tipo de activo',
                'valor_fiscal' => 'Valor fiscal',
                'depreciacion_acumulada' => 'Depreciación acumulada',
                'valor_en_libros' => 'Valor en libros',
                'valor_financiero' => 'Valor financiero',
                'vida_util_meses' => 'Vida útil meses',
                'fecha_corte' => 'Fecha de corte',
                'estatus_contable' => 'Estatus contable',
            ],
            'ubicacion_inventario' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'planta_nombre' => 'Planta',
                'area_nombre' => 'Área',
                'ubicacion_actual' => 'Ubicación actual',
                'responsable_nombre' => 'Responsable',
                'estatus_operativo' => 'Estatus operativo',
                'fecha_inventario' => 'Último inventario',
                'estatus_localizacion' => 'Localización',
                'inventario_observaciones' => 'Observaciones inventario',
                'fecha_movimiento' => 'Último movimiento',
                'movimiento_motivo' => 'Motivo movimiento',
            ],
            'activos_no_verificados' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'planta_nombre' => 'Planta',
                'area_nombre' => 'Área',
                'ubicacion_actual' => 'Ubicación actual',
                'responsable_nombre' => 'Responsable',
                'estatus_operativo' => 'Estatus operativo',
                'ultima_fecha_inventario' => 'Último inventario',
                'ultimo_estatus_localizacion' => 'Último estatus de inventario',
                'ultima_ubicacion_verificada' => 'Última ubicación verificada',
                'ultimo_verificado_por' => 'Último verificado por',
                'dias_desde_ultimo_inventario' => 'Días desde último inventario',
                'motivo_no_verificacion' => 'Motivo de pendiente',
            ],
            'discrepancias_inventario' => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'planta_nombre' => 'Planta',
                'area_nombre' => 'Área verificada',
                'ubicacion_registrada' => 'Ubicación registrada',
                'ubicacion_verificada' => 'Ubicación verificada',
                'fecha_inventario' => 'Fecha inventario',
                'estatus_localizacion' => 'Tipo de discrepancia',
                'inventario_observaciones' => 'Observaciones',
                'verificado_por_nombre' => 'Verificado por',
                'estatus_operativo' => 'Estatus operativo',
            ],
            'actividad_bitacora' => [
                'fecha_evento' => 'Fecha y hora',
                'usuario_nombre' => 'Usuario',
                'usuario_email' => 'Correo',
                'numero_activo' => 'Activo',
                'modulo' => 'Módulo',
                'accion' => 'Acción',
                'tabla_afectada' => 'Tabla afectada',
                'registro_clave' => 'Registro',
                'ip' => 'Dirección IP',
            ],
            default => [
                'numero_activo' => 'Activo',
                'activo_descripcion' => 'Descripción',
                'folio_factura' => 'Folio factura',
                'uuid_cfdi' => 'UUID CFDI',
                'proveedor_nombre' => 'Proveedor',
                'proveedor_rfc' => 'RFC',
                'planta_nombre' => 'Planta',
                'centro_costo_clave' => 'Centro de costo',
                'tipo_activo' => 'Tipo de activo',
                'fecha_factura' => 'Fecha factura',
                'monto_factura' => 'Monto',
                'moneda' => 'Moneda',
                'tiene_pdf' => 'PDF',
                'tiene_xml' => 'XML',
                'estatus_documental' => 'Estatus documental',
                'estatus_operativo' => 'Estatus operativo',
            ],
        };
    }

    private function selectedColumns(Request $request, array $availableColumns): array
    {
        $requested = $request->input('columnas');

        if (!is_array($requested) || empty($requested)) {
            return $this->defaultColumns($availableColumns);
        }

        $selected = [];

        foreach (array_values(array_unique($requested)) as $column) {
            if (is_string($column) && array_key_exists($column, $availableColumns)) {
                $selected[$column] = $availableColumns[$column];
            }
        }

        return !empty($selected)
            ? array_slice($selected, 0, 16, true)
            : $this->defaultColumns($availableColumns);
    }

    private function defaultColumns(array $availableColumns): array
    {
        return array_slice($availableColumns, 0, 11, true);
    }

    private function exportReport(
        Builder $query,
        Request $request,
        string $tipoReporte,
        array $columns,
        string $format,
        SimpleXlsxExporter $xlsxExporter,
        SimplePdfTableExporter $pdfExporter
    ): Response|BinaryFileResponse {
        if ($format === 'xlsx' && !$this->can('reportes.exportar_excel')) {
            abort(403, 'Tu usuario no tiene permiso para exportar reportes a Excel.');
        }

        if ($format === 'pdf' && !$this->can('reportes.exportar_pdf')) {
            abort(403, 'Tu usuario no tiene permiso para exportar reportes a PDF.');
        }

        $rows = (clone $query)
            ->limit(self::EXPORT_LIMIT + 1)
            ->get();

        if ($rows->count() > self::EXPORT_LIMIT) {
            return redirect()
                ->route('reportes', $request->except(['export']))
                ->withErrors([
                    'exportacion' => 'La exportación supera el límite de ' . number_format(self::EXPORT_LIMIT) . ' registros. Aplica filtros más específicos.',
                ]);
        }

        $dataRows = $this->rowsForExport($rows, array_keys($columns), $format);
        $filenameBase = 'reporte_swafi_' . $tipoReporte . '_' . now()->format('Ymd_His');
        $reportLabel = $this->reportDefinitions()[$tipoReporte]['label'] ?? 'Reporte SWAFI';

        $this->registerExportAudit($tipoReporte, $format, $request, $rows->count(), array_keys($columns));

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($columns, $dataRows) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($columns));

                foreach ($dataRows as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
            }, $filenameBase . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        if ($format === 'xlsx') {
            try {
                $path = $xlsxExporter->export($reportLabel, array_values($columns), $dataRows);
            } catch (\Throwable $exception) {
                return redirect()
                    ->route('reportes', $request->except(['export']))
                    ->withErrors(['exportacion' => $exception->getMessage()]);
            }

            return response()
                ->download($path, $filenameBase . '.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        }

        $pdf = $pdfExporter->export(
            title: $reportLabel,
            headers: array_values($columns),
            rows: $dataRows,
            metadata: [
                'usuario' => session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                'fecha' => now()->format('d/m/Y H:i:s'),
                'filtros' => $this->filterSummary($request),
            ]
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.pdf"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    private function rowsForExport(Collection $rows, array $columnKeys, string $format): array
    {
        $moneyKeys = [
            'monto_factura',
            'valor_fiscal',
            'depreciacion_acumulada',
            'valor_en_libros',
            'valor_financiero',
        ];

        return $rows->map(function ($row) use ($columnKeys, $moneyKeys, $format) {
            $line = [];

            foreach ($columnKeys as $key) {
                $value = data_get($row, $key);

                if (in_array($key, $moneyKeys, true) && $value !== null && $value !== '') {
                    $line[] = $format === 'xlsx'
                        ? (float) $value
                        : number_format((float) $value, 2, '.', ',');
                    continue;
                }

                if (str_contains($key, 'estatus') && $value !== null && $value !== '') {
                    $line[] = ucfirst(str_replace('_', ' ', (string) $value));
                    continue;
                }

                $line[] = $value;
            }

            return $line;
        })->all();
    }

    private function savedReports()
    {
        if (!Schema::hasTable('reportes_guardados')) {
            return collect();
        }

        return ReporteGuardado::query()
            ->where('user_id', $this->userId())
            ->orderByDesc('updated_at')
            ->get();
    }

    private function registerExportAudit(
        string $tipoReporte,
        string $format,
        Request $request,
        int $total,
        array $columns
    ): void {
        $action = match ($format) {
            'xlsx' => 'EXPORTACION_REPORTE_XLSX',
            'pdf' => 'EXPORTACION_REPORTE_PDF',
            default => 'EXPORTACION_REPORTE_CSV',
        };

        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $request->input('numero_activo'),
                'user_id' => $this->userId(),
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $action,
                'tabla_afectada' => 'reportes',
                'registro_clave' => $tipoReporte,
                'antes' => null,
                'despues' => json_encode([
                    'tipo_reporte' => $tipoReporte,
                    'formato' => strtoupper($format),
                    'total_exportado' => $total,
                    'columnas' => $columns,
                    'filtros' => $this->filtersForAudit($request),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La exportación no debe fallar por un error de bitácora.
        }
    }

    private function filtersForAudit(Request $request): array
    {
        return $request->except([
            '_token',
            'page',
            'export',
            'nombre_reporte_guardado',
            'columnas',
        ]);
    }

    private function filterSummary(Request $request): string
    {
        $filters = $this->filtersForAudit($request);
        $parts = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
        }

        return empty($parts) ? 'Sin filtros adicionales' : implode(' | ', $parts);
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

            'usuarios' => DB::table('users')
                ->where('estatus', 'activo')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
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
}
