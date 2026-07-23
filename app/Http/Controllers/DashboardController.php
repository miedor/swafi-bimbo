<?php

namespace App\Http\Controllers;

use App\Services\ObservationAssignmentQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ObservationAssignmentQueueService $observationAssignments
    ) {
    }

    public function index(Request $request)
    {
        $plantaId = $request->filled('planta_id') ? (int) $request->input('planta_id') : null;
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $expedientesAtencion = $this->expedientesAtencion($plantaId, $fechaDesde, $fechaHasta);
        $attentionQueue = $this->observationAttentionQueue();
        $validationQueue = $this->observationValidationQueue();
        $kpis = $this->buildKpis($plantaId, $fechaDesde, $fechaHasta);
        $kpis['observaciones_asignadas_atencion'] = $attentionQueue['total'];
        $kpis['observaciones_pendientes_validacion'] = $validationQueue['total'];

        return view('swafi.dashboard', [
            'filtros' => $request->all(),
            'catalogos' => $this->catalogos(),
            'kpis' => $kpis,
            'estatusDocumental' => $this->estatusDocumental($plantaId, $fechaDesde, $fechaHasta),
            'activosPorPlanta' => $this->activosPorPlanta(),
            'expedientesAtencion' => $expedientesAtencion,
            'observacionesAsignadasAtencion' => $attentionQueue['items'],
            'observacionesPendientesValidacion' => $validationQueue['items'],
            'actividadReciente' => $this->actividadReciente(),
            'ultimosDocumentos' => $this->ultimosDocumentos($plantaId),
        ]);
    }

    /**
     * @return array{total:int,items:\Illuminate\Support\Collection<int, object>}
     */
    private function observationAttentionQueue(): array
    {
        $roles = session('swafi_roles', []);
        $permissions = session('swafi_permissions', []);
        $isAdmin = in_array('Administrador SWAFI', $roles, true)
            || in_array('Administrador', $roles, true);
        $canAttend = $isAdmin
            || in_array('observaciones.atender', $permissions, true);
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        if (!$canAttend || $userId <= 0) {
            return [
                'total' => 0,
                'items' => collect(),
            ];
        }

        return $this->observationAssignments->pendingForUser($userId);
    }

    /**
     * @return array{total:int,items:\Illuminate\Support\Collection<int, object>}
     */
    private function observationValidationQueue(): array
    {
        $roles = session('swafi_roles', []);
        $permissions = session('swafi_permissions', []);
        $isAdmin = in_array('Administrador SWAFI', $roles, true)
            || in_array('Administrador', $roles, true);
        $canValidate = $isAdmin
            || in_array('observaciones.validar', $permissions, true);
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        if (
            !$canValidate
            || $userId <= 0
            || !Schema::hasTable('expediente_observaciones')
        ) {
            return [
                'total' => 0,
                'items' => collect(),
            ];
        }

        $query = DB::table('expediente_observaciones as o')
            ->join('expedientes as e', 'e.id', '=', 'o.expediente_id')
            ->join('activos as a', 'a.numero_activo', '=', 'o.numero_activo')
            ->leftJoin('users as ua', 'ua.id', '=', 'o.atendido_por')
            ->leftJoin('users as uasig', 'uasig.id', '=', 'o.asignado_a')
            ->whereNull('e.deleted_at')
            ->where('o.estatus', 'atendida');

        if (!$isAdmin) {
            $query->where('o.creado_por', $userId);
        }

        $query->select([
            'o.id as observacion_id',
            'o.expediente_id',
            'o.numero_activo',
            'o.tipo_observacion',
            'o.prioridad',
            'o.descripcion',
            'o.respuesta_atencion',
            'o.fecha_atencion',
            'e.folio_factura',
            'a.descripcion as activo_descripcion',
            'ua.name as atendido_por_nombre',
            'ua.email as atendido_por_email',
            'uasig.name as asignado_a_nombre',
            'uasig.email as asignado_a_email',
        ]);

        if (Schema::hasColumn('expediente_observaciones', 'fecha_notificacion_revision')) {
            $query->addSelect('o.fecha_notificacion_revision');
        } else {
            $query->addSelect(DB::raw('NULL as fecha_notificacion_revision'));
        }

        if (Schema::hasColumn('expediente_observaciones', 'notificacion_revision_error_referencia')) {
            $query->addSelect('o.notificacion_revision_error_referencia');
        } else {
            $query->addSelect(DB::raw('NULL as notificacion_revision_error_referencia'));
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('o.fecha_atencion')
            ->orderByDesc('o.updated_at')
            ->limit(8)
            ->get();

        return [
            'total' => $total,
            'items' => $items,
        ];
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
                    ->whereNull('v.deleted_at')
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
        $xmlValidados = $this->cfdiValidationCount($plantaId, ['valido', 'observado', 'invalido']);
        $cfdiConInconsistencias = $this->cfdiValidationCount($plantaId, ['observado', 'invalido']);
        $xmlSinValidar = max($documentosXml - $xmlValidados, 0);

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
            'xml_sin_validar' => $xmlSinValidar,
            'cfdi_con_inconsistencias' => $cfdiConInconsistencias,
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
            ->whereNull('deleted_at')
            ->select(
                'numero_activo',
                DB::raw('COUNT(*) as total_valores_registrados'),
                DB::raw("SUM(CASE WHEN estatus_contable = 'baja' OR (estatus_contable IN ('vigente', 'en_revision') AND COALESCE(valor_fiscal, 0) > 0 AND COALESCE(valor_financiero, 0) > 0) THEN 1 ELSE 0 END) as total_valores_validos")
            )
            ->groupBy('numero_activo');

        $cfdiCounts = DB::table('documentos_expediente as dx')
            ->leftJoin('cfdi_validaciones as cv', 'cv.documento_id', '=', 'dx.id')
            ->where('dx.vigente', true)
            ->whereRaw("UPPER(dx.tipo_documento) = 'XML'")
            ->select(
                'dx.expediente_id',
                DB::raw('COUNT(dx.id) as total_xml_cfdi'),
                DB::raw('COUNT(cv.id) as total_xml_validados'),
                DB::raw("SUM(CASE WHEN cv.estatus_validacion = 'valido' THEN 1 ELSE 0 END) as total_cfdi_validos"),
                DB::raw("SUM(CASE WHEN cv.estatus_validacion IN ('observado','invalido') THEN 1 ELSE 0 END) as total_cfdi_inconsistentes")
            )
            ->groupBy('dx.expediente_id');

        $latestInventoryIds = DB::table('inventarios_activo')
            ->select('numero_activo', DB::raw('MAX(id) as inventario_id'))
            ->groupBy('numero_activo');

        $query = DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->whereNull('e.deleted_at')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoinSub($documentCounts, 'dc', function ($join) {
                $join->on('dc.expediente_id', '=', 'e.id');
            })
            ->leftJoinSub($valorCounts, 'vc', function ($join) {
                $join->on('vc.numero_activo', '=', 'e.numero_activo');
            })
            ->leftJoinSub($cfdiCounts, 'cfc', function ($join) {
                $join->on('cfc.expediente_id', '=', 'e.id');
            })
            ->leftJoinSub($latestInventoryIds, 'li', function ($join) {
                $join->on('li.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('inventarios_activo as ia', 'ia.id', '=', 'li.inventario_id')
            ->where(function ($query) {
                $query->whereIn('e.estatus', ['incompleto', 'observado'])
                    ->orWhereRaw('COALESCE(dc.total_pdf, 0) = 0')
                    ->orWhereRaw('COALESCE(dc.total_xml, 0) = 0')
                    ->orWhereNull('a.ubicacion_id')
                    ->orWhereRaw('COALESCE(vc.total_valores_validos, 0) = 0')
                    ->orWhereRaw('COALESCE(cfc.total_xml_validados, 0) < COALESCE(cfc.total_xml_cfdi, 0)')
                    ->orWhereRaw('COALESCE(cfc.total_cfdi_inconsistentes, 0) > 0')
                    ->orWhereIn('ia.estatus_localizacion', ['no_encontrado', 'diferencia', 'pendiente']);
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
                DB::raw('COALESCE(cfc.total_xml_cfdi, 0) as total_xml_cfdi'),
                DB::raw('COALESCE(cfc.total_xml_validados, 0) as total_xml_validados'),
                DB::raw('COALESCE(cfc.total_cfdi_validos, 0) as total_cfdi_validos'),
                DB::raw('COALESCE(cfc.total_cfdi_inconsistentes, 0) as total_cfdi_inconsistentes'),
                'a.ubicacion_id',
                'ia.id as inventario_id',
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
                'ia.requiere_atencion as inventario_requiere_atencion',
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
            ->whereNull('e.deleted_at')
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
            ->whereNull('e.deleted_at')
            ->where('d.vigente', true)
            ->where('d.tipo_documento', $tipoDocumento);

        if ($plantaId) {
            $query->where('a.planta_id', $plantaId);
        }

        return $query->count();
    }


    private function cfdiValidationCount(?int $plantaId, array $statuses): int
    {
        $query = DB::table('cfdi_validaciones as cv')
            ->join('documentos_expediente as d', 'd.id', '=', 'cv.documento_id')
            ->join('expedientes as e', 'e.id', '=', 'cv.expediente_id')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->whereNull('e.deleted_at')
            ->where('d.vigente', true)
            ->whereIn('cv.estatus_validacion', $statuses);

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
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->whereNull('e.deleted_at');

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
