<?php

namespace App\Http\Controllers;

use App\Models\BusquedaGuardada;
use App\Services\AssetStatusCatalogService;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BusquedaController extends Controller
{
    public function __construct(private readonly AssetStatusCatalogService $statusCatalogs)
    {
    }

    private const CAMPOS_GUARDABLES = [
        'folio_factura',
        'uuid_cfdi',
        'proveedor',
        'rfc',
        'numero_activo',
        'planta_id',
        'centro_costo_id',
        'area_id',
        'ubicacion_id',
        'estatus',
        'estatus_operativo',
        'fecha_desde',
        'fecha_hasta',
        'monto_desde',
        'monto_hasta',
        'ordenar_por',
        'direccion',
        'per_page',
    ];

    public function index(
        Request $request,
        SimpleXlsxExporter $xlsxExporter,
        SimplePdfTableExporter $pdfExporter
    ) {
        $request->validate([
            'estatus' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('estatus_documentales', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'estatus_operativo' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('estatus_operativos', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
        ], [
            'estatus.exists' => 'El estatus documental seleccionado no existe o está inactivo.',
            'estatus_operativo.exists' => 'El estatus operativo seleccionado no existe o está inactivo.',
        ]);

        $query = $this->baseQuery();

        $this->applyFilters($query, $request);
        $this->applyOrder($query, $request);

        $exportFormat = strtolower((string) $request->input('export'));

        if (in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            return $this->exportResults(
                query: $query,
                request: $request,
                format: $exportFormat,
                xlsxExporter: $xlsxExporter,
                pdfExporter: $pdfExporter
            );
        }

        if ($this->hasMeaningfulFilters($request) && !$request->filled('page')) {
            $this->registrarBitacoraConsulta(
                accion: 'BUSQUEDA_AVANZADA',
                filtros: $this->filtersForAudit($request)
            );
        }

        $perPage = (int) $request->input('per_page', 10);

        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $resultados = $query
            ->paginate($perPage)
            ->withQueryString();

        return view('swafi.busqueda', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'busquedasGuardadas' => $this->busquedasGuardadas(),
            'camposGuardables' => self::CAMPOS_GUARDABLES,
            'canExportReports' => $this->canExportReports(),
        ]);
    }

    public function show(Request $request, ?int $expediente = null)
    {
        $activeTab = $this->resolveDetailTab((string) $request->input('tab', 'resumen'));

        if (!$expediente) {
            $expediente = DB::table('expedientes')->whereNull('deleted_at')->latest('id')->value('id');

            if (!$expediente) {
                return view('swafi.expediente', [
                    'expediente' => null,
                    'documentos' => $this->emptyPaginator('doc_page'),
                    'valor' => null,
                    'bitacora' => $this->emptyPaginator('audit_page'),
                    'observaciones' => $this->emptyPaginator('obs_page'),
                    'usuariosAsignablesObservacion' => collect(),
                    'cfdiValidaciones' => $this->emptyPaginator('cfdi_page'),
                    'inventarios' => $this->emptyPaginator('inv_page'),
                    'movimientos' => $this->emptyPaginator('mov_page'),
                    'activeTab' => $activeTab,
                    'resumenContadores' => [],
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
            ->whereNull('e.deleted_at')
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

        $documentosQuery = DB::table('documentos_expediente')
            ->where('expediente_id', $detalle->expediente_id)
            ->where('vigente', true)
            ->orderBy('tipo_documento')
            ->orderBy('nombre_archivo')
            ->orderByDesc('version');

        $documentos = $this->paginateForDetail(
            query: $documentosQuery,
            request: $request,
            perPage: 6,
            pageName: 'doc_page',
            tab: 'documentos'
        );

        $valor = DB::table('valores_activo')
            ->where('numero_activo', $detalle->numero_activo)
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_corte')
            ->orderByDesc('id')
            ->first();

        if (Schema::hasTable('cfdi_validaciones')) {
            $cfdiQuery = DB::table('cfdi_validaciones as cv')
                ->join('documentos_expediente as d', 'd.id', '=', 'cv.documento_id')
                ->leftJoin('users as uval', 'uval.id', '=', 'cv.validado_por')
                ->where('cv.expediente_id', $detalle->expediente_id)
                ->where('d.vigente', true)
                ->select([
                    'cv.*',
                    'd.nombre_archivo',
                    'd.version as documento_version',
                    'd.hash_sha256',
                    'uval.name as validado_por_nombre',
                    'uval.email as validado_por_email',
                ])
                ->orderByDesc('cv.validado_at');

            $cfdiValidaciones = $this->paginateForDetail(
                query: $cfdiQuery,
                request: $request,
                perPage: 4,
                pageName: 'cfdi_page',
                tab: 'documentos'
            );
        } else {
            $cfdiValidaciones = $this->emptyPaginator('cfdi_page');
        }

        $observaciones = $this->observacionesExpediente($detalle->expediente_id, $request);
        $inventarios = $this->inventariosExpediente($detalle->numero_activo, $request);
        $movimientos = $this->movimientosExpediente($detalle->numero_activo, $request);

        $bitacoraQuery = DB::table('bitacora_auditoria as b')
            ->leftJoin('users as ub', 'ub.id', '=', 'b.user_id')
            ->where('b.numero_activo', $detalle->numero_activo)
            ->select([
                'b.*',
                'ub.name as usuario_nombre',
                'ub.email as usuario_email',
            ])
            ->orderByDesc('b.fecha_evento')
            ->orderByDesc('b.id');

        $bitacora = $this->paginateForDetail(
            query: $bitacoraQuery,
            request: $request,
            perPage: 8,
            pageName: 'audit_page',
            tab: 'bitacora'
        );

        $resumenContadores = [
            'documentos' => DB::table('documentos_expediente')
                ->where('expediente_id', $detalle->expediente_id)
                ->where('vigente', true)
                ->count(),
            'cfdi' => Schema::hasTable('cfdi_validaciones')
                ? DB::table('cfdi_validaciones as cv')
                    ->join('documentos_expediente as d', 'd.id', '=', 'cv.documento_id')
                    ->where('cv.expediente_id', $detalle->expediente_id)
                    ->where('d.vigente', true)
                    ->count()
                : 0,
            'cfdi_observados' => Schema::hasTable('cfdi_validaciones')
                ? DB::table('cfdi_validaciones as cv')
                    ->join('documentos_expediente as d', 'd.id', '=', 'cv.documento_id')
                    ->where('cv.expediente_id', $detalle->expediente_id)
                    ->where('d.vigente', true)
                    ->where('cv.estatus_validacion', 'observado')
                    ->count()
                : 0,
            'cfdi_invalidos' => Schema::hasTable('cfdi_validaciones')
                ? DB::table('cfdi_validaciones as cv')
                    ->join('documentos_expediente as d', 'd.id', '=', 'cv.documento_id')
                    ->where('cv.expediente_id', $detalle->expediente_id)
                    ->where('d.vigente', true)
                    ->where('cv.estatus_validacion', 'invalido')
                    ->count()
                : 0,
            'observaciones' => Schema::hasTable('expediente_observaciones')
                ? DB::table('expediente_observaciones')
                    ->where('expediente_id', $detalle->expediente_id)
                    ->count()
                : 0,
            'observaciones_pendientes' => Schema::hasTable('expediente_observaciones')
                ? DB::table('expediente_observaciones')
                    ->where('expediente_id', $detalle->expediente_id)
                    ->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])
                    ->count()
                : 0,
            'inventarios' => DB::table('inventarios_activo')
                ->where('numero_activo', $detalle->numero_activo)
                ->count(),
            'discrepancias' => DB::table('inventarios_activo')
                ->where('numero_activo', $detalle->numero_activo)
                ->whereIn('estatus_localizacion', ['no_encontrado', 'diferencia', 'pendiente'])
                ->count(),
            'movimientos' => DB::table('movimientos_ubicacion')
                ->where('numero_activo', $detalle->numero_activo)
                ->count(),
            'bitacora' => DB::table('bitacora_auditoria')
                ->where('numero_activo', $detalle->numero_activo)
                ->count(),
        ];

        $this->registrarConsultaDetalle($request, $detalle);

        return view('swafi.expediente', [
            'expediente' => $detalle,
            'documentos' => $documentos,
            'valor' => $valor,
            'bitacora' => $bitacora,
            'observaciones' => $observaciones,
            'usuariosAsignablesObservacion' => $this->usuariosAsignablesObservacion(),
            'cfdiValidaciones' => $cfdiValidaciones,
            'inventarios' => $inventarios,
            'movimientos' => $movimientos,
            'activeTab' => $activeTab,
            'resumenContadores' => $resumenContadores,
        ]);
    }

    private function observacionesExpediente(int $expedienteId, Request $request): LengthAwarePaginator
    {
        if (!Schema::hasTable('expediente_observaciones')) {
            return $this->emptyPaginator('obs_page');
        }

        $hasAsignado = Schema::hasColumn('expediente_observaciones', 'asignado_a');
        $hasRolDestino = Schema::hasColumn('expediente_observaciones', 'rol_destino');
        $hasFechaAsignacion = Schema::hasColumn('expediente_observaciones', 'fecha_asignacion');
        $hasFechaNotificacion = Schema::hasColumn('expediente_observaciones', 'fecha_notificacion');
        $hasNotificacionError = Schema::hasColumn('expediente_observaciones', 'notificacion_error');

        $query = DB::table('expediente_observaciones as o')
            ->leftJoin('users as uc', 'uc.id', '=', 'o.creado_por')
            ->leftJoin('users as ua', 'ua.id', '=', 'o.atendido_por')
            ->leftJoin('users as uv', 'uv.id', '=', 'o.validado_por')
            ->leftJoin('users as ucan', 'ucan.id', '=', 'o.cancelado_por')
            ->where('o.expediente_id', $expedienteId);

        if ($hasAsignado) {
            $query->leftJoin('users as uasig', 'uasig.id', '=', 'o.asignado_a');
        }

        $selects = [
            'o.*',
            'uc.name as creado_por_nombre',
            'uc.email as creado_por_email',
            'ua.name as atendido_por_nombre',
            'ua.email as atendido_por_email',
            'uv.name as validado_por_nombre',
            'uv.email as validado_por_email',
            'ucan.name as cancelado_por_nombre',
            'ucan.email as cancelado_por_email',
        ];

        if ($hasAsignado) {
            $selects[] = 'uasig.name as asignado_a_nombre';
            $selects[] = 'uasig.email as asignado_a_email';
            $selects[] = 'uasig.usuario as asignado_a_usuario';
        } else {
            $selects[] = DB::raw('NULL as asignado_a_nombre');
            $selects[] = DB::raw('NULL as asignado_a_email');
            $selects[] = DB::raw('NULL as asignado_a_usuario');
            $selects[] = DB::raw('NULL as asignado_a');
        }

        if (!$hasRolDestino) {
            $selects[] = DB::raw('NULL as rol_destino');
        }

        if (!$hasFechaAsignacion) {
            $selects[] = DB::raw('NULL as fecha_asignacion');
        }

        if (!$hasFechaNotificacion) {
            $selects[] = DB::raw('NULL as fecha_notificacion');
        }

        if (!$hasNotificacionError) {
            $selects[] = DB::raw('NULL as notificacion_error');
        }

        $query->select($selects)
            ->orderByRaw("FIELD(o.estatus, 'rechazada', 'abierta', 'en_atencion', 'atendida', 'cerrada', 'cancelada')")
            ->orderByRaw("FIELD(o.prioridad, 'critica', 'alta', 'media', 'baja')")
            ->orderByDesc('o.updated_at');

        return $this->paginateForDetail(
            query: $query,
            request: $request,
            perPage: 5,
            pageName: 'obs_page',
            tab: 'observaciones'
        );
    }

    private function inventariosExpediente(string $numeroActivo, Request $request): LengthAwarePaginator
    {
        $query = DB::table('inventarios_activo as ia')
            ->leftJoin('ubicaciones as uv', 'uv.id', '=', 'ia.ubicacion_verificada_id')
            ->leftJoin('plantas as pv', 'pv.id', '=', 'uv.planta_id')
            ->leftJoin('areas as av', 'av.id', '=', 'uv.area_id')
            ->leftJoin('users as ver', 'ver.id', '=', 'ia.verificado_por')
            ->leftJoin('users as notif', 'notif.id', '=', 'ia.notificar_a')
            ->where('ia.numero_activo', $numeroActivo)
            ->select([
                'ia.*',
                'uv.codigo_interno as ubicacion_verificada_codigo',
                'uv.descripcion as ubicacion_verificada_descripcion',
                'pv.nombre as ubicacion_verificada_planta',
                'av.nombre as ubicacion_verificada_area',
                'ver.name as verificado_por_nombre',
                'ver.email as verificado_por_email',
                'notif.name as notificado_a_nombre',
                'notif.email as notificado_a_email',
            ])
            ->orderByDesc('ia.fecha_inventario')
            ->orderByDesc('ia.id');

        $paginator = $this->paginateForDetail(
            query: $query,
            request: $request,
            perPage: 5,
            pageName: 'inv_page',
            tab: 'ubicacion'
        );

        if (!Schema::hasTable('inventario_evidencias')) {
            foreach ($paginator->items() as $item) {
                $item->evidencias = collect();
            }

            return $paginator;
        }

        $inventarioIds = collect($paginator->items())
            ->pluck('id')
            ->filter()
            ->values();

        $evidencias = $inventarioIds->isEmpty()
            ? collect()
            : DB::table('inventario_evidencias as ie')
                ->leftJoin('users as ue', 'ue.id', '=', 'ie.cargado_por')
                ->whereIn('ie.inventario_id', $inventarioIds)
                ->where('ie.vigente', true)
                ->select([
                    'ie.*',
                    'ue.name as cargado_por_nombre',
                    'ue.email as cargado_por_email',
                ])
                ->orderBy('ie.tipo_evidencia')
                ->orderBy('ie.nombre_archivo')
                ->get()
                ->groupBy('inventario_id');

        foreach ($paginator->items() as $item) {
            $item->evidencias = $evidencias->get($item->id, collect());
        }

        return $paginator;
    }

    private function movimientosExpediente(string $numeroActivo, Request $request): LengthAwarePaginator
    {
        $query = DB::table('movimientos_ubicacion as mu')
            ->leftJoin('ubicaciones as uo', 'uo.id', '=', 'mu.ubicacion_origen_id')
            ->leftJoin('ubicaciones as ud', 'ud.id', '=', 'mu.ubicacion_destino_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'mu.responsable_id')
            ->leftJoin('users as ur', 'ur.id', '=', 'mu.registrado_por')
            ->where('mu.numero_activo', $numeroActivo)
            ->select([
                'mu.*',
                'uo.codigo_interno as origen_codigo',
                'uo.descripcion as origen_descripcion',
                'ud.codigo_interno as destino_codigo',
                'ud.descripcion as destino_descripcion',
                'r.nombre as responsable_nombre',
                'ur.name as registrado_por_nombre',
                'ur.email as registrado_por_email',
            ])
            ->orderByDesc('mu.fecha_movimiento')
            ->orderByDesc('mu.id');

        return $this->paginateForDetail(
            query: $query,
            request: $request,
            perPage: 5,
            pageName: 'mov_page',
            tab: 'ubicacion'
        );
    }

    private function paginateForDetail(
        $query,
        Request $request,
        int $perPage,
        string $pageName,
        string $tab
    ): LengthAwarePaginator {
        $paginator = $query->paginate($perPage, ['*'], $pageName);
        $pageNames = ['doc_page', 'cfdi_page', 'obs_page', 'inv_page', 'mov_page', 'audit_page'];
        $queryParams = $request->except($pageNames);
        $queryParams['tab'] = $tab;

        return $paginator->appends($queryParams);
    }

    private function emptyPaginator(string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: 1,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    private function resolveDetailTab(string $tab): string
    {
        $allowed = ['resumen', 'ubicacion', 'documentos', 'observaciones', 'bitacora'];

        return in_array($tab, $allowed, true) ? $tab : 'resumen';
    }

    private function registrarConsultaDetalle(Request $request, object $detalle): void
    {
        foreach (['doc_page', 'cfdi_page', 'obs_page', 'inv_page', 'mov_page', 'audit_page'] as $pageName) {
            if ($request->filled($pageName)) {
                return;
            }
        }

        $sessionKey = 'swafi_consulta_expediente_' . $detalle->expediente_id;
        $lastRegisteredAt = (int) $request->session()->get($sessionKey, 0);

        if ($lastRegisteredAt > 0 && (now()->timestamp - $lastRegisteredAt) < 300) {
            return;
        }

        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $detalle->numero_activo,
                'user_id' => $this->userId(),
                'modulo' => 'M03 Consultas',
                'accion' => 'CONSULTA',
                'tabla_afectada' => 'expedientes',
                'registro_clave' => (string) $detalle->expediente_id,
                'antes' => null,
                'despues' => json_encode([
                    'folio_factura' => $detalle->folio_factura,
                    'numero_activo' => $detalle->numero_activo,
                    'tab_inicial' => $request->input('tab', 'resumen'),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $request->session()->put($sessionKey, now()->timestamp);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'asset_detail_audit_write',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );
        }
    }

    private function usuariosAsignablesObservacion()
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles') || !Schema::hasTable('role_user')) {
            return collect();
        }

        return DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->whereIn('r.nombre', ['Usuario Captura', 'Usuario Planta / Inventarios'])
            ->select([
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
                'r.nombre as rol_nombre',
            ])
            ->orderBy('r.nombre')
            ->orderBy('u.name')
            ->get();
    }

    private function baseQuery()
    {
        return DB::table('expedientes as e')
            ->whereNull('e.deleted_at')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->select([
                'e.id as expediente_id',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                'e.estatus',
                'e.created_at as expediente_creado',
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'cc.clave as centro_costo_clave',
                'cc.descripcion as centro_costo_descripcion',
                'pl.nombre as planta_nombre',
                'u.id as ubicacion_id',
                'u.codigo_interno as ubicacion_codigo',
                'u.descripcion as ubicacion_descripcion',
                'ar.id as area_id',
                'ar.nombre as area_nombre',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('folio_factura')) {
            $query->where('e.folio_factura', 'like', '%' . trim((string) $request->input('folio_factura')) . '%');
        }

        if ($request->filled('uuid_cfdi')) {
            $query->where('e.uuid_cfdi', 'like', '%' . trim((string) $request->input('uuid_cfdi')) . '%');
        }

        if ($request->filled('numero_activo')) {
            $query->where('a.numero_activo', 'like', '%' . trim((string) $request->input('numero_activo')) . '%');
        }

        if ($request->filled('proveedor')) {
            $query->where('p.nombre', 'like', '%' . trim((string) $request->input('proveedor')) . '%');
        }

        if ($request->filled('rfc')) {
            $query->where('p.rfc', 'like', '%' . trim((string) $request->input('rfc')) . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', (int) $request->input('planta_id'));
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('a.centro_costo_id', (int) $request->input('centro_costo_id'));
        }

        if ($request->filled('area_id')) {
            $query->where('u.area_id', (int) $request->input('area_id'));
        }

        if ($request->filled('ubicacion_id')) {
            $query->where('a.ubicacion_id', (int) $request->input('ubicacion_id'));
        }

        if ($request->filled('estatus')) {
            $query->where('e.estatus', $request->input('estatus'));
        }

        if ($request->filled('estatus_operativo')) {
            $query->where('a.estatus_operativo', $request->input('estatus_operativo'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('e.fecha_factura', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('e.fecha_factura', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('monto_desde')) {
            $query->where('e.monto_factura', '>=', (float) $request->input('monto_desde'));
        }

        if ($request->filled('monto_hasta')) {
            $query->where('e.monto_factura', '<=', (float) $request->input('monto_hasta'));
        }
    }

    private function applyOrder($query, Request $request): void
    {
        $allowed = [
            'fecha_factura' => 'e.fecha_factura',
            'fecha_registro' => 'e.created_at',
            'numero_activo' => 'a.numero_activo',
            'folio_factura' => 'e.folio_factura',
            'proveedor' => 'p.nombre',
            'planta' => 'pl.nombre',
            'monto_factura' => 'e.monto_factura',
            'estatus' => 'e.estatus',
        ];

        $orderKey = (string) $request->input('ordenar_por', 'fecha_factura');
        $direction = strtolower((string) $request->input('direccion', 'desc'));

        if (!array_key_exists($orderKey, $allowed)) {
            $orderKey = 'fecha_factura';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($allowed[$orderKey], $direction)
            ->orderByDesc('e.id');
    }

    private function catalogos(): array
    {
        return [
            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'centrosCosto' => DB::table('centros_costo')
                ->where('estatus', 'activo')
                ->orderBy('clave')
                ->get(),

            'areas' => DB::table('areas as ar')
                ->leftJoin('plantas as pl', 'pl.id', '=', 'ar.planta_id')
                ->where('ar.estatus', 'activo')
                ->select([
                    'ar.id',
                    'ar.nombre',
                    'ar.planta_id',
                    'pl.nombre as planta_nombre',
                ])
                ->orderBy('pl.nombre')
                ->orderBy('ar.nombre')
                ->get(),

            'ubicaciones' => DB::table('ubicaciones as u')
                ->leftJoin('plantas as pl', 'pl.id', '=', 'u.planta_id')
                ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
                ->where('u.estatus', 'activo')
                ->select([
                    'u.id',
                    'u.codigo_interno',
                    'u.descripcion',
                    'u.planta_id',
                    'u.area_id',
                    'pl.nombre as planta_nombre',
                    'ar.nombre as area_nombre',
                ])
                ->orderBy('pl.nombre')
                ->orderBy('ar.nombre')
                ->orderBy('u.codigo_interno')
                ->get(),

            'estatusDocumentales' => $this->statusCatalogs->documentaryOptions(),
            'estatusOperativos' => $this->statusCatalogs->operationalOptions(),
        ];
    }

    private function busquedasGuardadas()
    {
        if (!Schema::hasTable('busquedas_guardadas')) {
            return collect();
        }

        return BusquedaGuardada::query()
            ->where('user_id', $this->userId())
            ->where('modulo', 'busqueda')
            ->orderByDesc('updated_at')
            ->get();
    }

    private function exportResults(
        $query,
        Request $request,
        string $format,
        SimpleXlsxExporter $xlsxExporter,
        SimplePdfTableExporter $pdfExporter
    ) {
        if (!$this->canExportReports()) {
            abort(403, 'Tu usuario no tiene permiso para exportar resultados de búsqueda.');
        }

        $limit = 5000;
        $rows = (clone $query)
            ->limit($limit + 1)
            ->get();

        if ($rows->count() > $limit) {
            return redirect()
                ->route('busqueda', $request->except(['export']))
                ->withErrors([
                    'exportacion' => 'La exportación supera el límite de ' . number_format($limit) . ' registros. Aplica filtros más específicos.',
                ]);
        }

        $columns = $this->exportColumns();
        $dataRows = $this->rowsForExport($rows, array_keys($columns), $format);
        $filtros = $this->filtersForAudit($request);
        $filenameBase = 'consulta_swafi_' . now()->format('Ymd_His');

        $this->registrarBitacoraConsulta(
            accion: match ($format) {
                'xlsx' => 'EXPORTACION_BUSQUEDA_XLSX',
                'pdf' => 'EXPORTACION_BUSQUEDA_PDF',
                default => 'EXPORTACION_BUSQUEDA_CSV',
            },
            filtros: [
                'filtros' => $filtros,
                'total_exportado' => $rows->count(),
                'formato' => strtoupper($format),
                'columnas' => array_keys($columns),
            ]
        );

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
                $contents = $xlsxExporter->exportBytes(
                    'Búsqueda avanzada',
                    array_values($columns),
                    $dataRows
                );
            } catch (\Throwable $exception) {
                $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                    $exception,
                    'advanced_search_excel_export',
                    [
                        'user_id' => auth()->id(),
                        'route_name' => $request->route()?->getName(),
                    ]
                );

                return redirect()
                    ->route('busqueda', $request->except(['export']))
                    ->withErrors([
                        'exportacion' => "No fue posible generar el archivo Excel. Referencia: {$reference}.",
                    ]);
            }

            return response()->streamDownload(
                static function () use ($contents): void {
                    echo $contents;
                },
                $filenameBase . '.xlsx',
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        $pdf = $pdfExporter->export(
            title: 'Resultados de búsqueda avanzada SWAFI',
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

    private function exportColumns(): array
    {
        return [
            'folio_factura' => 'Folio factura',
            'uuid_cfdi' => 'UUID CFDI',
            'numero_activo' => 'Número activo',
            'activo_descripcion' => 'Descripción activo',
            'proveedor_nombre' => 'Proveedor',
            'proveedor_rfc' => 'RFC',
            'planta_nombre' => 'Planta',
            'centro_costo_clave' => 'Centro de costo',
            'area_nombre' => 'Área',
            'ubicacion_resumen' => 'Ubicación',
            'fecha_factura' => 'Fecha factura',
            'monto_factura' => 'Monto',
            'moneda' => 'Moneda',
            'estatus' => 'Estatus documental',
            'estatus_operativo' => 'Estatus operativo',
        ];
    }

    private function rowsForExport($rows, array $columnKeys, string $format): array
    {
        return $rows->map(function ($row) use ($columnKeys, $format) {
            $row->ubicacion_resumen = trim(implode(' / ', array_filter([
                $row->ubicacion_codigo,
                $row->ubicacion_descripcion,
                $row->area_nombre,
            ])));

            $line = [];

            foreach ($columnKeys as $key) {
                $value = data_get($row, $key);

                if ($key === 'monto_factura' && $value !== null && $value !== '') {
                    $line[] = $format === 'xlsx'
                        ? (float) $value
                        : number_format((float) $value, 2, '.', ',');
                    continue;
                }

                if (in_array($key, ['estatus', 'estatus_operativo'], true) && $value !== null && $value !== '') {
                    $line[] = ucfirst(str_replace('_', ' ', (string) $value));
                    continue;
                }

                $line[] = $value;
            }

            return $line;
        })->all();
    }

    private function filterSummary(Request $request): string
    {
        $filters = $this->filtersForAudit($request);
        $parts = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
        }

        return empty($parts) ? 'Sin filtros adicionales' : implode(' | ', $parts);
    }

    private function canExportReports(): bool
    {
        $roles = session('swafi_roles', []);
        $permissions = session('swafi_permissions', []);

        return in_array('Administrador SWAFI', $roles, true)
            || in_array('reportes.exportar', $permissions, true);
    }

    private function hasMeaningfulFilters(Request $request): bool
    {
        foreach (self::CAMPOS_GUARDABLES as $field) {
            if (in_array($field, ['ordenar_por', 'direccion', 'per_page'], true)) {
                continue;
            }

            if ($request->filled($field)) {
                return true;
            }
        }

        return false;
    }

    private function filtersForAudit(Request $request): array
    {
        $filters = [];

        foreach (self::CAMPOS_GUARDABLES as $field) {
            if ($request->filled($field)) {
                $filters[$field] = $request->input($field);
            }
        }

        return $filters;
    }

    private function registrarBitacoraConsulta(string $accion, array $filtros): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $filtros['numero_activo'] ?? null,
                'user_id' => $this->userId(),
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'accion' => $accion,
                'tabla_afectada' => 'expedientes',
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode($filtros, JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'search_audit_write',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );
        }
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}
