<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventarioActivoRequest;
use App\Http\Requests\StoreMovimientoUbicacionRequest;
use App\Mail\SwafiDiscrepanciaInventarioMail;
use App\Models\Activo;
use App\Models\InventarioActivo;
use App\Models\InventarioEvidencia;
use App\Models\MovimientoUbicacion;
use App\Services\InventoryPeriodService;
use App\Services\SwafiAuthorizationService;
use App\Services\SwafiStorageService;
use App\Services\TransferWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UbicacionInventarioController extends Controller
{
    public function __construct(
        private readonly SwafiStorageService $storage,
        private readonly TransferWorkflowService $transferWorkflow,
        private readonly InventoryPeriodService $inventoryPeriods,
        private readonly SwafiAuthorizationService $authorization
    ) {
    }

    private const DISCREPANCY_STATUSES = [
        'no_encontrado',
        'diferencia',
        'pendiente',
    ];

    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $perPage = (int) $request->input('per_page', 10);

        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $resultados = $query
            ->orderBy('pl.nombre')
            ->orderBy('a.numero_activo')
            ->paginate($perPage)
            ->withQueryString();

        $canManageLocations = $this->authorization->canCurrentUser('ubicaciones.administrar');
        $canApproveTransfers = $this->authorization->canCurrentUser('ubicaciones.aprobar_traslados');
        $canManageInventoryPeriods = $this->authorization->canCurrentUser('ubicaciones.cerrar_inventario');

        return view('swafi.ubicacion', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'solicitudesTraslado' => $this->transferRequests($canApproveTransfers),
            'periodosInventario' => $this->inventoryPeriodsList(),
            'pendingTransfersCount' => $this->pendingTransferCount($canApproveTransfers),
            'blockedPeriodsCount' => $this->blockedPeriodCount(),
            'canManageLocations' => $canManageLocations,
            'canApproveTransfers' => $canApproveTransfers,
            'canManageInventoryPeriods' => $canManageInventoryPeriods,
        ]);
    }

    public function storeMovimiento(StoreMovimientoUbicacionRequest $request)
    {
        $data = $request->validated();

        $result = $this->transferWorkflow->registerMovementOrTransfer(
            data: $data,
            userId: $this->userId()
        );

        $redirectParameters = [
            'numero_activo' => $data['numero_activo'],
        ];

        if ($result['type'] === 'transfer_request') {
            $redirectParameters['panel'] = 'traslados';
        }

        return redirect()
            ->route('ubicacion', $redirectParameters)
            ->with(
                $result['type'] === 'transfer_request' ? 'warning' : 'success',
                $result['message']
            );
    }

    public function storeInventario(StoreInventarioActivoRequest $request)
    {
        $data = $request->validated();
        $isDiscrepancy = in_array($data['estatus_localizacion'], self::DISCREPANCY_STATUSES, true);
        $recipient = $isDiscrepancy
            ? $this->resolveAuditRecipient((int) $data['notificar_a'])
            : null;

        $storedPaths = [];
        $inventario = null;
        $activo = null;
        $evidenceCount = 0;

        DB::beginTransaction();

        try {
            $activo = Activo::where('numero_activo', $data['numero_activo'])
                ->lockForUpdate()
                ->firstOrFail();

            $antes = $activo->toArray();

            $this->inventoryPeriods->assertInventoryAllowed(
                asset: $activo,
                verifiedLocationId: !empty($data['ubicacion_verificada_id'])
                    ? (int) $data['ubicacion_verificada_id']
                    : null,
                date: $data['fecha_inventario'],
                updateLocation: $request->boolean('actualizar_ubicacion')
            );

            $inventario = InventarioActivo::create([
                'numero_activo' => $activo->numero_activo,
                'fecha_inventario' => $data['fecha_inventario'],
                'estatus_localizacion' => $data['estatus_localizacion'],
                'ubicacion_verificada_id' => $data['ubicacion_verificada_id'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'verificado_por' => $this->userId(),
                'notificar_a' => $recipient?->id,
                'notificado_at' => null,
                'notificacion_error' => null,
                'requiere_atencion' => $isDiscrepancy,
            ]);

            foreach ($request->file('evidencias', []) as $file) {
                $stored = $this->storeInventoryEvidenceFile(
                    file: $file,
                    numeroActivo: $activo->numero_activo,
                    inventarioId: (int) $inventario->id
                );

                $storedPaths[] = $stored;
                $mimeType = $stored['mime_type'];

                InventarioEvidencia::create([
                    'inventario_id' => $inventario->id,
                    'numero_activo' => $activo->numero_activo,
                    'tipo_evidencia' => str_starts_with($mimeType, 'image/') ? 'FOTO' : 'DOCUMENTO',
                    'nombre_archivo' => $file->getClientOriginalName(),
                    'ruta_archivo' => $stored['path'],
                    'storage_disk' => $stored['disk'],
                    'mime_type' => $stored['mime_type'],
                    'tamano_bytes' => $stored['tamano_bytes'],
                    'hash_sha256' => $stored['hash_sha256'],
                    'vigente' => true,
                    'cargado_por' => $this->userId(),
                ]);

                $evidenceCount++;
            }

            $actualizoUbicacion = false;

            if (
                $request->boolean('actualizar_ubicacion')
                && !empty($data['ubicacion_verificada_id'])
            ) {
                $activo->update([
                    'ubicacion_id' => $data['ubicacion_verificada_id'],
                    'actualizado_por' => $this->userId(),
                ]);

                $actualizoUbicacion = true;
            }

            $despues = $activo->fresh()->toArray();

            $this->registrarBitacora(
                numeroActivo: $activo->numero_activo,
                accion: $isDiscrepancy ? 'REGISTRO_DISCREPANCIA_INVENTARIO' : 'REGISTRO_INVENTARIO',
                tablaAfectada: 'inventarios_activo',
                registroClave: (string) $inventario->id,
                antes: [
                    'activo' => $antes,
                ],
                despues: [
                    'activo' => $despues,
                    'inventario' => $inventario->toArray(),
                    'actualizo_ubicacion' => $actualizoUbicacion,
                    'total_evidencias' => $evidenceCount,
                    'notificar_a' => $recipient?->email,
                ]
            );

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            foreach ($storedPaths as $storedFile) {
                $this->storage->delete($storedFile['disk'], $storedFile['path']);
            }

            throw $exception;
        }

        $notificationWarning = null;

        if ($isDiscrepancy && $recipient && $inventario && $activo) {
            $notificationWarning = $this->sendDiscrepancyNotification(
                inventario: $inventario,
                activo: $activo->fresh(),
                recipient: $recipient,
                evidenceCount: $evidenceCount
            );
        }

        $redirect = redirect()
            ->route('ubicacion', [
                'numero_activo' => $data['numero_activo'],
            ])
            ->with(
                'success',
                $isDiscrepancy
                    ? 'La discrepancia de inventario se registró con evidencia y trazabilidad.'
                    : 'La toma de inventario se registró correctamente.'
            );

        if ($notificationWarning) {
            $redirect->with('warning', $notificationWarning);
        }

        return $redirect;
    }

    private function baseQuery()
    {
        $latestInventarios = DB::table('inventarios_activo')
            ->select('numero_activo', DB::raw('MAX(id) as inventario_id'))
            ->groupBy('numero_activo');

        $latestMovimientos = DB::table('movimientos_ubicacion')
            ->select('numero_activo', DB::raw('MAX(id) as movimiento_id'))
            ->groupBy('numero_activo');

        $evidenceCounts = DB::table('inventario_evidencias')
            ->where('vigente', true)
            ->select('inventario_id', DB::raw('COUNT(*) as total_evidencias'))
            ->groupBy('inventario_id');

        $latestExpedientes = DB::table('expedientes')
            ->whereNull('deleted_at')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        $pendingTransfers = DB::table('solicitudes_traslado')
            ->where('estatus', 'pendiente')
            ->select('numero_activo', DB::raw('MIN(id) as solicitud_id'))
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoinSub($latestExpedientes, 'le', function ($join) {
                $join->on('le.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoinSub($latestInventarios, 'li', function ($join) {
                $join->on('li.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('inventarios_activo as ia', 'ia.id', '=', 'li.inventario_id')
            ->leftJoin('ubicaciones as uv', 'uv.id', '=', 'ia.ubicacion_verificada_id')
            ->leftJoin('users as un', 'un.id', '=', 'ia.notificar_a')
            ->leftJoinSub($evidenceCounts, 'ec', function ($join) {
                $join->on('ec.inventario_id', '=', 'ia.id');
            })
            ->leftJoinSub($latestMovimientos, 'lm', function ($join) {
                $join->on('lm.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('movimientos_ubicacion as mu', 'mu.id', '=', 'lm.movimiento_id')
            ->leftJoin('ubicaciones as ud', 'ud.id', '=', 'mu.ubicacion_destino_id')
            ->leftJoinSub($pendingTransfers, 'pt', function ($join) {
                $join->on('pt.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('solicitudes_traslado as st', 'st.id', '=', 'pt.solicitud_id')
            ->leftJoin('ubicaciones as std', 'std.id', '=', 'st.ubicacion_destino_id')
            ->leftJoin('plantas as stp', 'stp.id', '=', 'std.planta_id')
            ->select([
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'a.ubicacion_id',
                'a.responsable_id',
                'le.expediente_id',

                'pl.id as planta_id',
                'pl.nombre as planta_nombre',

                'ar.id as area_id',
                'ar.nombre as area_nombre',

                'u.codigo_interno as ubicacion_codigo',
                'u.descripcion as ubicacion_descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',

                'r.nombre as responsable_nombre',
                'r.correo as responsable_correo',

                'ia.id as inventario_id',
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
                'ia.observaciones as inventario_observaciones',
                'ia.requiere_atencion',
                'ia.notificado_at',
                'ia.notificacion_error',
                'un.name as notificado_a_nombre',
                'un.email as notificado_a_email',
                DB::raw('COALESCE(ec.total_evidencias, 0) as total_evidencias'),
                'uv.codigo_interno as ubicacion_verificada_codigo',
                'uv.descripcion as ubicacion_verificada_descripcion',

                'mu.id as movimiento_id',
                'mu.fecha_movimiento',
                'mu.motivo as movimiento_motivo',
                'ud.codigo_interno as ubicacion_destino_codigo',
                'ud.descripcion as ubicacion_destino_descripcion',

                'st.id as solicitud_traslado_id',
                'st.uuid as solicitud_traslado_uuid',
                'st.fecha_movimiento as solicitud_traslado_fecha',
                'st.motivo as solicitud_traslado_motivo',
                'std.codigo_interno as solicitud_destino_codigo',
                'std.descripcion as solicitud_destino_descripcion',
                'stp.nombre as solicitud_destino_planta',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('numero_activo')) {
            $query->where('a.numero_activo', 'like', '%' . trim((string) $request->numero_activo) . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', (int) $request->planta_id);
        }

        if ($request->filled('area_id')) {
            $query->where('u.area_id', (int) $request->area_id);
        }

        if ($request->filled('ubicacion_id')) {
            $query->where('a.ubicacion_id', (int) $request->ubicacion_id);
        }

        if ($request->filled('responsable_id')) {
            $query->where('a.responsable_id', (int) $request->responsable_id);
        }

        if ($request->filled('estatus_operativo')) {
            $query->where('a.estatus_operativo', $request->estatus_operativo);
        }

        if ($request->filled('estatus_localizacion')) {
            if ($request->estatus_localizacion === 'sin_inventario') {
                $query->whereNull('ia.id');
            } else {
                $query->where('ia.estatus_localizacion', $request->estatus_localizacion);
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('ia.fecha_inventario', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('ia.fecha_inventario', '<=', $request->fecha_hasta);
        }
    }

    private function catalogos(): array
    {
        return [
            'activos' => DB::table('activos')
                ->where('activo', true)
                ->select('numero_activo', 'descripcion')
                ->orderBy('numero_activo')
                ->get(),

            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'areas' => DB::table('areas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'ubicaciones' => DB::table('ubicaciones as u')
                ->leftJoin('plantas as pl', 'pl.id', '=', 'u.planta_id')
                ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
                ->where('u.estatus', 'activo')
                ->where('pl.estatus', 'activo')
                ->orderBy('pl.nombre')
                ->orderBy('ar.nombre')
                ->orderBy('u.codigo_interno')
                ->select([
                    'u.id',
                    'u.planta_id',
                    'u.codigo_interno',
                    'u.descripcion',
                    'u.edificio',
                    'u.piso',
                    'u.pasillo',
                    'pl.nombre as planta_nombre',
                    'ar.nombre as area_nombre',
                ])
                ->get(),

            'responsables' => DB::table('responsables')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'usuariosContabilidad' => $this->auditRecipients(),
        ];
    }

    private function transferRequests(bool $canApproveTransfers)
    {
        $query = DB::table('solicitudes_traslado as st')
            ->join('activos as a', 'a.numero_activo', '=', 'st.numero_activo')
            ->leftJoin('plantas as pa', 'pa.id', '=', 'a.planta_id')
            ->leftJoin('ubicaciones as uo', 'uo.id', '=', 'st.ubicacion_origen_id')
            ->leftJoin('plantas as po', 'po.id', '=', 'uo.planta_id')
            ->join('ubicaciones as ud', 'ud.id', '=', 'st.ubicacion_destino_id')
            ->join('plantas as pd', 'pd.id', '=', 'ud.planta_id')
            ->leftJoin('responsables as rd', 'rd.id', '=', 'st.responsable_destino_id')
            ->leftJoin('users as us', 'us.id', '=', 'st.solicitado_por')
            ->leftJoin('users as ur', 'ur.id', '=', 'st.resuelto_por')
            ->select([
                'st.id',
                'st.uuid',
                'st.numero_activo',
                'a.descripcion as activo_descripcion',
                'st.fecha_movimiento',
                'st.motivo',
                'st.evidencia',
                'st.estatus',
                'st.solicitado_at',
                'st.resuelto_at',
                'st.comentario_resolucion',
                'st.movimiento_id',
                'uo.codigo_interno as origen_codigo',
                'uo.descripcion as origen_descripcion',
                DB::raw('COALESCE(po.nombre, pa.nombre) as origen_planta'),
                'ud.codigo_interno as destino_codigo',
                'ud.descripcion as destino_descripcion',
                'pd.nombre as destino_planta',
                'rd.nombre as responsable_destino',
                'us.name as solicitado_por_nombre',
                'us.email as solicitado_por_email',
                'ur.name as resuelto_por_nombre',
            ]);

        if (!$canApproveTransfers) {
            $query->where('st.solicitado_por', $this->userId());
        }

        return $query
            ->orderByRaw("CASE WHEN st.estatus = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('st.solicitado_at')
            ->paginate(5, ['*'], 'traslados_page')
            ->withQueryString();
    }

    private function inventoryPeriodsList()
    {
        return DB::table('periodos_inventario as pi')
            ->join('plantas as p', 'p.id', '=', 'pi.planta_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'pi.creado_por')
            ->leftJoin('users as ub', 'ub.id', '=', 'pi.bloqueado_por')
            ->leftJoin('users as ud', 'ud.id', '=', 'pi.desbloqueado_por')
            ->select([
                'pi.id',
                'pi.uuid',
                'pi.planta_id',
                'p.nombre as planta_nombre',
                'pi.nombre',
                'pi.fecha_inicio',
                'pi.fecha_fin',
                'pi.estatus',
                'pi.observaciones',
                'pi.motivo_bloqueo',
                'pi.bloqueado_at',
                'pi.desbloqueado_at',
                'uc.name as creado_por_nombre',
                'ub.name as bloqueado_por_nombre',
                'ud.name as desbloqueado_por_nombre',
            ])
            ->orderByRaw("CASE WHEN pi.estatus = 'bloqueado' THEN 0 ELSE 1 END")
            ->orderByDesc('pi.fecha_inicio')
            ->paginate(5, ['*'], 'periodos_page')
            ->withQueryString();
    }

    private function pendingTransferCount(bool $canApproveTransfers): int
    {
        $query = DB::table('solicitudes_traslado')
            ->where('estatus', 'pendiente');

        if (!$canApproveTransfers) {
            $query->where('solicitado_por', $this->userId());
        }

        return $query->count();
    }

    private function blockedPeriodCount(): int
    {
        return DB::table('periodos_inventario')
            ->where('estatus', 'bloqueado')
            ->count();
    }

    private function auditRecipients()
    {
        return DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->whereIn('r.nombre', ['Usuario Consulta / Auditoría', 'Usuario Consulta / Auditoria'])
            ->select([
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
            ])
            ->distinct()
            ->orderBy('u.name')
            ->get();
    }

    private function resolveAuditRecipient(int $userId): object
    {
        $recipient = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('u.id', $userId)
            ->where('u.estatus', 'activo')
            ->where('r.activo', 1)
            ->whereIn('r.nombre', ['Usuario Consulta / Auditoría', 'Usuario Consulta / Auditoria'])
            ->select([
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
            ])
            ->first();

        if (!$recipient) {
            throw ValidationException::withMessages([
                'notificar_a' => 'La persona seleccionada debe ser un usuario activo con rol Usuario Consulta / Auditoría.',
            ]);
        }

        return $recipient;
    }

    /**
     * @return array{disk:string,path:string,mime_type:string,tamano_bytes:int,hash_sha256:string}
     */
    private function storeInventoryEvidenceFile($file, string $numeroActivo, int $inventarioId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'dat');
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = Str::slug($baseName) ?: 'evidencia';
        $storedName = now()->format('YmdHis')
            . '_'
            . Str::random(10)
            . '_'
            . $safeBase
            . '.'
            . $extension;

        $directory = 'swafi/inventarios/'
            . Str::slug($numeroActivo)
            . '/'
            . $inventarioId;

        return $this->storage->storeUploadedFile(
            file: $file,
            directory: $directory,
            storedName: $storedName
        );
    }

    private function sendDiscrepancyNotification(
        InventarioActivo $inventario,
        Activo $activo,
        object $recipient,
        int $evidenceCount
    ): ?string {
        $registeredLocation = $this->locationDescription($activo->ubicacion_id);
        $verifiedLocation = $this->locationDescription($inventario->ubicacion_verificada_id);
        $expedienteId = DB::table('expedientes')
            ->where('numero_activo', $activo->numero_activo)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('id');

        $detailUrl = $expedienteId
            ? route('expediente', [
                'expediente' => $expedienteId,
                'tab' => 'ubicacion',
            ])
            : route('ubicacion', [
                'numero_activo' => $activo->numero_activo,
            ]);

        try {
            Mail::to($recipient->email)->send(new SwafiDiscrepanciaInventarioMail(
                recipientName: $recipient->name ?: $recipient->email,
                reportedBy: session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                numeroActivo: $activo->numero_activo,
                descripcionActivo: $activo->descripcion,
                fechaInventario: Carbon::parse($inventario->fecha_inventario)->format('d/m/Y'),
                estatusLocalizacion: $this->statusLabel($inventario->estatus_localizacion),
                ubicacionRegistrada: $registeredLocation,
                ubicacionVerificada: $verifiedLocation,
                observaciones: (string) ($inventario->observaciones ?: 'Sin observaciones adicionales.'),
                evidenceCount: $evidenceCount,
                detailUrl: $detailUrl
            ));

            $inventario->update([
                'notificado_at' => now(),
                'notificacion_error' => null,
            ]);

            $this->registrarBitacora(
                numeroActivo: $activo->numero_activo,
                accion: 'NOTIFICACION_DISCREPANCIA_ENVIADA',
                tablaAfectada: 'inventarios_activo',
                registroClave: (string) $inventario->id,
                antes: null,
                despues: [
                    'destinatario' => $recipient->email,
                    'fecha_notificacion' => now()->toDateTimeString(),
                ]
            );

            return null;
        } catch (\Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 1500);

            $inventario->update([
                'notificacion_error' => $error,
            ]);

            $this->registrarBitacora(
                numeroActivo: $activo->numero_activo,
                accion: 'NOTIFICACION_DISCREPANCIA_FALLIDA',
                tablaAfectada: 'inventarios_activo',
                registroClave: (string) $inventario->id,
                antes: null,
                despues: [
                    'destinatario' => $recipient->email,
                    'error' => $error,
                ]
            );

            return 'La discrepancia se guardó, pero el correo no pudo enviarse. El error quedó registrado para revisión administrativa.';
        }
    }

    private function locationDescription(?int $locationId): string
    {
        if (!$locationId) {
            return 'Sin ubicación registrada';
        }

        $location = DB::table('ubicaciones as u')
            ->leftJoin('plantas as p', 'p.id', '=', 'u.planta_id')
            ->leftJoin('areas as a', 'a.id', '=', 'u.area_id')
            ->where('u.id', $locationId)
            ->select([
                'u.codigo_interno',
                'u.descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',
                'p.nombre as planta_nombre',
                'a.nombre as area_nombre',
            ])
            ->first();

        if (!$location) {
            return 'Ubicación no disponible';
        }

        return implode(' / ', array_filter([
            $location->planta_nombre,
            $location->area_nombre,
            $location->codigo_interno,
            $location->descripcion,
            $location->edificio,
            $location->piso,
            $location->pasillo,
        ]));
    }

    private function exportCsv($query)
    {
        $rows = $query
            ->orderBy('pl.nombre')
            ->orderBy('a.numero_activo')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Numero activo',
                'Descripcion',
                'Planta',
                'Area',
                'Ubicacion actual',
                'Responsable',
                'Estatus operativo',
                'Fecha inventario',
                'Estatus localizacion',
                'Evidencias vigentes',
                'Notificado a',
                'Fecha notificacion',
                'Ultimo movimiento',
                'Motivo movimiento',
                'Traslado pendiente',
                'Planta destino solicitada',
                'Fecha traslado solicitada',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->numero_activo,
                    $row->activo_descripcion,
                    $row->planta_nombre,
                    $row->area_nombre,
                    $this->formatUbicacion($row),
                    $row->responsable_nombre,
                    $row->estatus_operativo,
                    $row->fecha_inventario,
                    $row->estatus_localizacion,
                    $row->total_evidencias,
                    $row->notificado_a_email,
                    $row->notificado_at,
                    $row->fecha_movimiento,
                    $row->movimiento_motivo,
                    $row->solicitud_traslado_uuid,
                    $row->solicitud_destino_planta,
                    $row->solicitud_traslado_fecha,
                ]);
            }

            fclose($output);
        }, 'ubicacion_inventario_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatUbicacion($row): string
    {
        $parts = array_filter([
            $row->ubicacion_codigo,
            $row->ubicacion_descripcion,
            $row->edificio,
            $row->piso,
            $row->pasillo,
        ]);

        return $parts ? implode(' / ', $parts) : 'Sin ubicación';
    }

    private function statusLabel(string $status): string
    {
        return [
            'localizado' => 'Localizado',
            'no_encontrado' => 'No encontrado',
            'diferencia' => 'Diferencia de ubicación',
            'pendiente' => 'Pendiente de revisión',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => $this->userId(),
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_clave' => $registroClave,
            'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}
