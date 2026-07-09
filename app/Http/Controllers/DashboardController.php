<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $plantaId = $request->filled('planta_id') ? (int) $request->input('planta_id') : null;
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $expedientesAtencion = $this->expedientesAtencion($plantaId, $fechaDesde, $fechaHasta);

        return view('swafi.dashboard', [
            'filtros' => $request->all(),
            'catalogos' => $this->catalogos(),
            'kpis' => $this->buildKpis($plantaId, $fechaDesde, $fechaHasta),
            'estatusDocumental' => $this->estatusDocumental($plantaId, $fechaDesde, $fechaHasta),
            'activosPorPlanta' => $this->activosPorPlanta(),
            'expedientesAtencion' => $expedientesAtencion,
            'actividadReciente' => $this->actividadReciente(),
            'ultimosDocumentos' => $this->ultimosDocumentos($plantaId),
        ]);
    }

    private function catalogos(): array
    {
        return [
            'plantas' => DB::table('plantas')
                ->orderBy('nombre')
                ->get(),
        ];
    }

    private function buildKpis(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $totalActivos = $this->activosBase($plantaId)->count();

        $totalExpedientes = $this->expedientesBase($plantaId, $fechaDesde, $fechaHasta)->count();

        $expedientesCompletos = $this->expedientesBase($plantaId, $fechaDesde, $fechaHasta)
            ->where('e.estatus', 'completo')
            ->count();

        $expedientesIncompletos = $this->expedientesBase($plantaId, $fechaDesde, $fechaHasta)
            ->whereIn('e.estatus', ['incompleto', 'observado'])
            ->count();

        $activosSinUbicacion = $this->activosBase($plantaId)
            ->whereNull('a.ubicacion_id')
            ->count();

        $activosSinValores = $this->activosBase($plantaId)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('valores_activo as v')
                    ->whereColumn('v.numero_activo', 'a.numero_activo')
                    ->where(function ($subquery) {
                        $subquery->where('v.estatus_contable', 'baja')
                            ->orWhere(function ($nested) {
                                $nested->whereIn('v.estatus_contable', ['vigente', 'en_revision'])
                                    ->whereRaw('COALESCE(v.valor_fiscal, 0) > 0')
                                    ->whereRaw('COALESCE(v.valor_financiero, 0) > 0');
                            });
                    });
            })
            ->count();

        $montoTotal = $this->expedientesBase($plantaId, $fechaDesde, $fechaHasta)
            ->sum('e.monto_factura');

        $documentosPdf = $this->documentosVigentesPorTipo('PDF', $plantaId);
        $documentosXml = $this->documentosVigentesPorTipo('XML', $plantaId);

        $eventosAuditoria = DB::table('bitacora_auditoria')->count();

        $totalAtencion = $this->expedientesAtencionTotal($plantaId, $fechaDesde, $fechaHasta);

        return [
            'total_activos' => $totalActivos,
            'total_expedientes' => $totalExpedientes,
            'expedientes_completos' => $expedientesCompletos,
            'expedientes_incompletos' => $expedientesIncompletos,
            'activos_sin_ubicacion' => $activosSinUbicacion,
            'activos_sin_valores' => $activosSinValores,
            'monto_total' => $montoTotal,
            'documentos_pdf' => $documentosPdf,
            'documentos_xml' => $documentosXml,
            'eventos_auditoria' => $eventosAuditoria,
            'total_atencion' => $totalAtencion,
            'porcentaje_completos' => $totalExpedientes > 0
                ? round(($expedientesCompletos / $totalExpedientes) * 100, 1)
                : 0,
            'porcentaje_incompletos' => $totalExpedientes > 0
                ? round(($expedientesIncompletos / $totalExpedientes) * 100, 1)
                : 0,
        ];
    }

    private function estatusDocumental(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta)
    {
        return $this->expedientesBase($plantaId, $fechaDesde, $fechaHasta)
            ->select('e.estatus', DB::raw('COUNT(*) as total'))
            ->groupBy('e.estatus')
            ->orderByDesc('total')
            ->get();
    }

    private function activosPorPlanta()
    {
        return DB::table('activos as a')
            ->leftJoin('plantas as p', 'p.id', '=', 'a.planta_id')
            ->where('a.activo', true)
            ->select(
                DB::raw("COALESCE(p.nombre, 'Sin planta') as planta_nombre"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('p.nombre')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function expedientesAtencion(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta)
    {
        return $this->expedientesAtencionQuery($plantaId, $fechaDesde, $fechaHasta)
            ->orderByDesc('e.updated_at')
            ->orderByDesc('e.created_at')
            ->limit(8)
            ->get();
    }

    private function expedientesAtencionTotal(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta): int
    {
        return $this->expedientesAtencionQuery($plantaId, $fechaDesde, $fechaHasta)->count();
    }

    private function expedientesAtencionQuery(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta)
    {
        $documentCounts = DB::table('documentos_expediente')
            ->select(
                'expediente_id',
                DB::raw("SUM(CASE WHEN tipo_documento = 'PDF' AND vigente = 1 THEN 1 ELSE 0 END) as total_pdf"),
                DB::raw("SUM(CASE WHEN tipo_documento = 'XML' AND vigente = 1 THEN 1 ELSE 0 END) as total_xml")
            )
            ->groupBy('expediente_id');

        $valorCounts = DB::table('valores_activo')
            ->select(
                'numero_activo',
                DB::raw('COUNT(*) as total_valores_registrados'),
                DB::raw("SUM(CASE WHEN estatus_contable = 'baja' OR (estatus_contable IN ('vigente', 'en_revision') AND COALESCE(valor_fiscal, 0) > 0 AND COALESCE(valor_financiero, 0) > 0) THEN 1 ELSE 0 END) as total_valores_validos")
            )
            ->groupBy('numero_activo');

        $query = DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoinSub($documentCounts, 'dc', function ($join) {
                $join->on('dc.expediente_id', '=', 'e.id');
            })
            ->leftJoinSub($valorCounts, 'vc', function ($join) {
                $join->on('vc.numero_activo', '=', 'e.numero_activo');
            })
            ->where(function ($query) {
                $query->whereIn('e.estatus', ['incompleto', 'observado'])
                    ->orWhereRaw('COALESCE(dc.total_pdf, 0) = 0')
                    ->orWhereRaw('COALESCE(dc.total_xml, 0) = 0')
                    ->orWhereNull('a.ubicacion_id')
                    ->orWhereRaw('COALESCE(vc.total_valores_validos, 0) = 0');
            })
            ->select([
                'e.id as expediente_id',
                'e.numero_activo',
                'a.descripcion as activo_descripcion',
                'e.folio_factura',
                'e.estatus',
                'p.nombre as proveedor_nombre',
                'pl.nombre as planta_nombre',
                DB::raw('COALESCE(dc.total_pdf, 0) as total_pdf'),
                DB::raw('COALESCE(dc.total_xml, 0) as total_xml'),
                DB::raw('COALESCE(vc.total_valores_registrados, 0) as total_valores_registrados'),
                DB::raw('COALESCE(vc.total_valores_validos, 0) as total_valores'),
                'a.ubicacion_id',
                'e.fecha_factura',
                'e.created_at',
                'e.updated_at',
            ]);

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        if ($fechaDesde) {
            $query->whereDate('e.fecha_factura', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('e.fecha_factura', '<=', $fechaHasta);
        }

        return $query;
    }

    private function actividadReciente()
    {
        return DB::table('bitacora_auditoria as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->select([
                'b.id',
                'b.numero_activo',
                'b.modulo',
                'b.accion',
                'b.tabla_afectada',
                'b.registro_clave',
                'b.ip',
                'b.fecha_evento',
                'u.name as usuario_nombre',
                'u.email as usuario_email',
            ])
            ->orderByDesc('b.fecha_evento')
            ->limit(8)
            ->get();
    }

    private function ultimosDocumentos(?int $plantaId)
    {
        $query = DB::table('documentos_expediente as d')
            ->join('expedientes as e', 'e.id', '=', 'd.expediente_id')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->select([
                'd.id',
                'd.nombre_archivo',
                'd.tipo_documento',
                'd.version',
                'd.vigente',
                'd.created_at',
                'e.id as expediente_id',
                'e.numero_activo',
                'e.folio_factura',
                'a.descripcion as activo_descripcion',
            ])
            ->where('d.vigente', true);

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        return $query
            ->orderByDesc('d.created_at')
            ->limit(6)
            ->get();
    }

    private function documentosVigentesPorTipo(string $tipoDocumento, ?int $plantaId): int
    {
        $query = DB::table('documentos_expediente as d')
            ->join('expedientes as e', 'e.id', '=', 'd.expediente_id')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->where('d.vigente', true)
            ->where('d.tipo_documento', $tipoDocumento);

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        return $query->count();
    }

    private function activosBase(?int $plantaId)
    {
        $query = DB::table('activos as a')
            ->where('a.activo', true);

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        return $query;
    }

    private function expedientesBase(?int $plantaId, ?string $fechaDesde, ?string $fechaHasta)
    {
        $query = DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo');

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        if ($fechaDesde) {
            $query->whereDate('e.fecha_factura', '>=', $fechaDesde);
        }

        if ($fechaHasta) {
            $query->whereDate('e.fecha_factura', '<=', $fechaHasta);
        }

        return $query;
    }
}
