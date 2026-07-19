<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecurityIndexRequest;
use App\Http\Requests\StoreSecurityPermissionRequest;
use App\Http\Requests\StoreSecurityRoleRequest;
use App\Http\Requests\StoreSecurityUserRequest;
use App\Http\Requests\UpdateSecurityPermissionStatusRequest;
use App\Http\Requests\UpdateSecurityRoleStatusRequest;
use App\Http\Requests\UpdateSecurityUserStatusRequest;
use App\Services\AuditLogService;
use App\Services\RolePermissionManagementService;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\UserAccessManagementService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeguridadController extends Controller
{
    public function __construct(
        private readonly UserAccessManagementService $userAccess,
        private readonly RolePermissionManagementService $rolePermissions,
        private readonly AuditLogService $auditLog,
        private readonly SimpleXlsxExporter $xlsxExporter,
        private readonly SimplePdfTableExporter $pdfExporter
    ) {
    }

    public function index(SecurityIndexRequest $request)
    {
        $filters = $request->validated();
        $tabActivo = $this->normalizeTab($filters['tab'] ?? 'usuarios');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sessionRoles = collect($request->session()->get('swafi_roles', []))
            ->map(fn ($role) => mb_strtolower(trim((string) $role)));
        $sessionPermissions = collect($request->session()->get('swafi_permissions', []))
            ->map(fn ($permission) => trim((string) $permission));
        $canManageSecurity = $sessionRoles->contains('administrador swafi')
            || $sessionPermissions->contains('seguridad.administrar');
        $canViewAudit = $sessionRoles->contains('administrador swafi')
            || $sessionPermissions->contains('bitacora.ver');

        $usuariosQuery = $this->usuariosQuery($request);
        $bitacoraQuery = $this->auditLog->query($filters);
        $permissionsAsignables = DB::table('permissions')
            ->where('activo', 1)
            ->orderBy('clave')
            ->get(['id', 'clave', 'descripcion', 'activo', 'es_sistema']);

        $exportFormat = (string) ($filters['export'] ?? '');

        if ($tabActivo === 'usuarios' && $exportFormat === 'csv') {
            return $this->exportUsuariosCsv($usuariosQuery);
        }

        if ($tabActivo === 'roles' && $exportFormat === 'csv') {
            return $this->exportRolesCsv();
        }

        if ($tabActivo === 'bitacora' && in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            return $this->exportBitacora($exportFormat, $filters, $request);
        }

        return view('swafi.seguridad', [
            'tabActivo' => $tabActivo,
            'usuarios' => $usuariosQuery
                ->paginate($perPage, ['*'], 'usuarios_page')
                ->withQueryString(),
            'roles' => $this->rolesWithPermissions(),
            'rolesAsignables' => DB::table('roles')
                ->where('activo', 1)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'activo', 'es_sistema']),
            'permissionsByModule' => $permissionsAsignables
                ->groupBy(fn ($permission) => explode('.', (string) $permission->clave, 2)[0]),
            'permissions' => $this->permissionsWithUsage(),
            'bitacora' => $bitacoraQuery
                ->paginate($perPage, ['*'], 'bitacora_page')
                ->withQueryString(),
            'bitacoraDetalle' => $tabActivo === 'bitacora'
                ? $this->auditLog->detail(
                    isset($filters['detalle_bitacora'])
                        ? (int) $filters['detalle_bitacora']
                        : null
                )
                : null,
            'bitacoraOpciones' => $tabActivo === 'bitacora'
                ? $this->auditLog->filterOptions()
                : ['users' => collect(), 'modules' => collect(), 'actions' => collect()],
            'bitacoraExportLimit' => $this->auditLog->exportLimit(),
            'canManageSecurity' => $canManageSecurity,
            'canViewAudit' => $canViewAudit,
            'usuarioEdit' => $this->findUserForEdit($filters['editar_usuario'] ?? null),
            'rolEdit' => $this->findRoleForEdit($filters['editar_rol'] ?? null),
            'permisoEdit' => $this->findPermissionForEdit($filters['editar_permiso'] ?? null),
            'usuarioRoles' => $this->userRolesForEdit($filters['editar_usuario'] ?? null),
            'rolPermisos' => $this->rolePermissionsForEdit($filters['editar_rol'] ?? null),
            'filtros' => $filters,
            'kpis' => $this->buildKpis(),
        ]);
    }

    public function storeUser(StoreSecurityUserRequest $request)
    {
        try {
            $result = $this->userAccess->saveUser(
                validated: $request->validated(),
                actorId: (int) $request->session()->get('swafi_user_id'),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return back()
                ->withErrors(['usuario' => $exception->getMessage()])
                ->withInput($request->except('password'));
        }

        $message = $result['created']
            ? 'El usuario se creó correctamente con sus roles asignados.'
            : 'El usuario y sus roles se actualizaron correctamente.';

        if (!$result['created'] && ($result['authorization_changed'] || $result['password_changed'])) {
            $message .= ' Sus sesiones anteriores fueron revocadas para aplicar el cambio de seguridad.';
        }

        return redirect()
            ->route('seguridad', ['tab' => 'usuarios'])
            ->with('success', $message);
    }

    public function destroyUser(UpdateSecurityUserStatusRequest $request, int $user)
    {
        return $this->changeUserStatus($request, $user, 'inactivo');
    }

    public function activateUser(UpdateSecurityUserStatusRequest $request, int $user)
    {
        return $this->changeUserStatus($request, $user, 'activo');
    }

    private function changeUserStatus(
        UpdateSecurityUserStatusRequest $request,
        int $user,
        string $expectedStatus
    ) {
        $validated = $request->validated();

        if (($validated['estatus'] ?? null) !== $expectedStatus) {
            return redirect()
                ->route('seguridad', ['tab' => 'usuarios'])
                ->withErrors([
                    'usuario' => 'La operación solicitada no coincide con el estatus indicado.',
                ]);
        }

        try {
            $this->userAccess->changeStatus(
                targetUserId: $user,
                nextStatus: $expectedStatus,
                actorId: (int) $request->session()->get('swafi_user_id'),
                reason: $validated['motivo'] ?? null,
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('seguridad', ['tab' => 'usuarios'])
                ->withErrors(['usuario' => $exception->getMessage()]);
        }

        return redirect()
            ->route('seguridad', ['tab' => 'usuarios'])
            ->with(
                'success',
                $expectedStatus === 'activo'
                    ? 'El usuario fue activado correctamente.'
                    : 'El usuario fue desactivado y sus sesiones fueron revocadas.'
            );
    }

    public function storeRole(StoreSecurityRoleRequest $request)
    {
        try {
            $result = $this->rolePermissions->saveRole(
                validated: $request->validated(),
                actorId: (int) $request->session()->get('swafi_user_id'),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return back()
                ->withErrors(['rol' => $exception->getMessage()])
                ->withInput();
        }

        $message = $result['created']
            ? 'El rol se creó correctamente con sus permisos asignados.'
            : 'El rol y su matriz de permisos se actualizaron correctamente.';

        if (!$result['created'] && $result['permissions_changed'] && $result['affected_users'] > 0) {
            $message .= ' Los cambios de autorización se reflejarán en la siguiente solicitud de los usuarios asignados.';
        }

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with('success', $message);
    }

    public function destroyRole(UpdateSecurityRoleStatusRequest $request, int $role)
    {
        return $this->changeRoleStatus($request, $role, 'inactivo');
    }

    public function activateRole(UpdateSecurityRoleStatusRequest $request, int $role)
    {
        return $this->changeRoleStatus($request, $role, 'activo');
    }

    private function changeRoleStatus(
        UpdateSecurityRoleStatusRequest $request,
        int $role,
        string $expectedStatus
    ) {
        $validated = $request->validated();

        if (($validated['estatus'] ?? null) !== $expectedStatus) {
            return redirect()
                ->route('seguridad', ['tab' => 'roles'])
                ->withErrors(['rol' => 'La operación solicitada no coincide con el estatus indicado.']);
        }

        try {
            $this->rolePermissions->changeRoleStatus(
                roleId: $role,
                nextStatus: $expectedStatus,
                reason: (string) $validated['motivo'],
                actorId: (int) $request->session()->get('swafi_user_id'),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('seguridad', ['tab' => 'roles'])
                ->withErrors(['rol' => $exception->getMessage()]);
        }

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with(
                'success',
                $expectedStatus === 'activo'
                    ? 'El rol fue activado correctamente.'
                    : 'El rol fue desactivado correctamente.'
            );
    }

    public function storePermission(StoreSecurityPermissionRequest $request)
    {
        try {
            $result = $this->rolePermissions->savePermission(
                validated: $request->validated(),
                actorId: (int) $request->session()->get('swafi_user_id'),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return back()
                ->withErrors(['permiso' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with(
                'success',
                $result['created']
                    ? 'El permiso se creó correctamente y quedó disponible para los roles.'
                    : 'La descripción del permiso se actualizó correctamente.'
            );
    }

    public function destroyPermission(UpdateSecurityPermissionStatusRequest $request, int $permission)
    {
        return $this->changePermissionStatus($request, $permission, 'inactivo');
    }

    public function activatePermission(UpdateSecurityPermissionStatusRequest $request, int $permission)
    {
        return $this->changePermissionStatus($request, $permission, 'activo');
    }

    private function changePermissionStatus(
        UpdateSecurityPermissionStatusRequest $request,
        int $permission,
        string $expectedStatus
    ) {
        $validated = $request->validated();

        if (($validated['estatus'] ?? null) !== $expectedStatus) {
            return redirect()
                ->route('seguridad', ['tab' => 'roles'])
                ->withErrors(['permiso' => 'La operación solicitada no coincide con el estatus indicado.']);
        }

        try {
            $this->rolePermissions->changePermissionStatus(
                permissionId: $permission,
                nextStatus: $expectedStatus,
                reason: (string) $validated['motivo'],
                actorId: (int) $request->session()->get('swafi_user_id'),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('seguridad', ['tab' => 'roles'])
                ->withErrors(['permiso' => $exception->getMessage()]);
        }

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with(
                'success',
                $expectedStatus === 'activo'
                    ? 'El permiso fue activado y se agregó al rol Administrador SWAFI.'
                    : 'El permiso fue desactivado correctamente.'
            );
    }

    private function normalizeTab(?string $tab): string
    {
        return in_array($tab, ['usuarios', 'roles', 'bitacora'], true)
            ? $tab
            : 'usuarios';
    }

    private function usuariosQuery(Request $request)
    {
        $query = DB::table('users as u')
            ->leftJoin('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->leftJoin('roles as r', 'r.id', '=', 'ru.role_id')
            ->select([
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
                'u.estatus',
                'u.ultimo_acceso',
                'u.ultimo_ip',
                'u.intentos_fallidos',
                'u.ultimo_intento_fallido',
                'u.bloqueado_en',
                'u.bloqueado_motivo',
                'u.created_at',
                'u.updated_at',
                DB::raw("COALESCE(GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', '), 'Sin rol') as roles"),
            ])
            ->groupBy(
                'u.id',
                'u.usuario',
                'u.name',
                'u.email',
                'u.estatus',
                'u.ultimo_acceso',
                'u.ultimo_ip',
                'u.intentos_fallidos',
                'u.ultimo_intento_fallido',
                'u.bloqueado_en',
                'u.bloqueado_motivo',
                'u.created_at',
                'u.updated_at'
            );

        if ($request->filled('buscar')) {
            $buscar = '%' . trim($request->input('buscar')) . '%';

            $query->where(function ($where) use ($buscar) {
                $where->where('u.usuario', 'like', $buscar)
                    ->orWhere('u.name', 'like', $buscar)
                    ->orWhere('u.email', 'like', $buscar);
            });
        }

        if ($request->filled('rol_id')) {
            $query->where('r.id', $request->input('rol_id'));
        }

        if ($request->filled('estatus')) {
            $query->where('u.estatus', $request->input('estatus'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('u.ultimo_acceso', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('u.ultimo_acceso', '<=', $request->input('fecha_hasta'));
        }

        return $query->orderBy('u.name');
    }

    private function rolesWithPermissions()
    {
        return DB::table('roles as r')
            ->leftJoin('permission_role as pr', 'pr.role_id', '=', 'r.id')
            ->leftJoin('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->leftJoin('role_user as ru', 'ru.role_id', '=', 'r.id')
            ->leftJoin('users as u', 'u.id', '=', 'ru.user_id')
            ->select([
                'r.id',
                'r.nombre',
                'r.descripcion',
                'r.activo',
                'r.es_sistema',
                'r.created_at',
                'r.updated_at',
                DB::raw(
                    "COALESCE(
                        GROUP_CONCAT(
                            DISTINCT CASE WHEN p.activo = 1 THEN p.clave END
                            ORDER BY p.clave SEPARATOR ', '
                        ),
                        'Sin permisos activos'
                    ) as permisos"
                ),
                DB::raw('COUNT(DISTINCT CASE WHEN p.activo = 1 THEN p.id END) as total_permisos'),
                DB::raw('COUNT(DISTINCT u.id) as total_usuarios'),
                DB::raw("COUNT(DISTINCT CASE WHEN u.estatus = 'activo' THEN u.id END) as usuarios_activos"),
            ])
            ->groupBy(
                'r.id',
                'r.nombre',
                'r.descripcion',
                'r.activo',
                'r.es_sistema',
                'r.created_at',
                'r.updated_at'
            )
            ->orderBy('r.nombre')
            ->get();
    }

    private function permissionsWithUsage()
    {
        return DB::table('permissions as p')
            ->leftJoin('permission_role as pr', 'pr.permission_id', '=', 'p.id')
            ->leftJoin('roles as r', 'r.id', '=', 'pr.role_id')
            ->select([
                'p.id',
                'p.clave',
                'p.descripcion',
                'p.activo',
                'p.es_sistema',
                'p.created_at',
                'p.updated_at',
                DB::raw(
                    "COALESCE(
                        GROUP_CONCAT(
                            DISTINCT CASE
                                WHEN r.nombre <> 'Administrador SWAFI' THEN r.nombre
                            END
                            ORDER BY r.nombre SEPARATOR ', '
                        ),
                        'Solo Administrador SWAFI'
                    ) as roles_asignados"
                ),
                DB::raw(
                    "COUNT(
                        DISTINCT CASE
                            WHEN r.nombre <> 'Administrador SWAFI' THEN r.id
                        END
                    ) as total_roles_no_admin"
                ),
            ])
            ->groupBy(
                'p.id',
                'p.clave',
                'p.descripcion',
                'p.activo',
                'p.es_sistema',
                'p.created_at',
                'p.updated_at'
            )
            ->orderBy('p.clave')
            ->get();
    }

    private function findUserForEdit(?string $id)
    {
        return $id
            ? DB::table('users')->where('id', $id)->first()
            : null;
    }

    private function findRoleForEdit(?string $id)
    {
        return $id
            ? DB::table('roles')->where('id', $id)->first()
            : null;
    }

    private function findPermissionForEdit(?string $id)
    {
        return $id
            ? DB::table('permissions')->where('id', $id)->first()
            : null;
    }

    private function userRolesForEdit(?string $id): array
    {
        if (!$id) {
            return [];
        }

        return DB::table('role_user')
            ->where('user_id', $id)
            ->pluck('role_id')
            ->map(fn ($roleId) => (string) $roleId)
            ->all();
    }

    private function rolePermissionsForEdit(?string $id): array
    {
        if (!$id) {
            return [];
        }

        return DB::table('permission_role')
            ->where('role_id', $id)
            ->pluck('permission_id')
            ->map(fn ($permissionId) => (string) $permissionId)
            ->all();
    }

    private function buildKpis(): array
    {
        return [
            'usuarios_total' => DB::table('users')->count(),
            'usuarios_activos' => DB::table('users')->where('estatus', 'activo')->count(),
            'usuarios_bloqueados' => DB::table('users')->where('estatus', 'bloqueado')->count(),
            'roles_activos' => DB::table('roles')->where('activo', 1)->count(),
            'permisos_total' => DB::table('permissions')->where('activo', 1)->count(),
            'eventos_bitacora' => DB::table('bitacora_auditoria')->count(),
        ];
    }

    private function exportUsuariosCsv($query)
    {
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Usuario',
                'Nombre',
                'Correo',
                'Roles',
                'Estatus',
                'Último acceso',
                'Última IP',
                'Intentos fallidos',
                'Bloqueado en',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->usuario,
                    $row->name,
                    $row->email,
                    $row->roles,
                    $row->estatus,
                    $row->ultimo_acceso,
                    $row->ultimo_ip,
                    $row->intentos_fallidos ?? 0,
                    $row->bloqueado_en ?? null,
                ]);
            }

            fclose($output);
        }, 'seguridad_usuarios_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportRolesCsv()
    {
        $rows = $this->rolesWithPermissions();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Rol',
                'Descripción',
                'Tipo',
                'Activo',
                'Usuarios asignados',
                'Usuarios activos',
                'Total permisos activos',
                'Permisos activos',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->nombre,
                    $row->descripcion,
                    ((int) $row->es_sistema) === 1 ? 'Base del sistema' : 'Personalizado',
                    ((int) $row->activo) === 1 ? 'Sí' : 'No',
                    $row->total_usuarios,
                    $row->usuarios_activos,
                    $row->total_permisos,
                    $row->permisos,
                ]);
            }

            fclose($output);
        }, 'seguridad_roles_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportBitacora(
        string $format,
        array $filters,
        SecurityIndexRequest $request
    ) {
        $redirectFilters = collect($filters)
            ->except(['export', 'detalle_bitacora'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $redirectFilters['tab'] = 'bitacora';

        try {
            $export = $this->auditLog->rowsForExport($filters);
            $rows = $export['rows']->map(function ($row): array {
                return [
                    $row->fecha_evento,
                    $row->usuario ?: ($row->usuario_email ?: 'Sistema'),
                    $row->usuario_nombre ?: 'Sin nombre',
                    $row->modulo,
                    $row->accion,
                    $row->tabla_afectada ?: '—',
                    $row->registro_clave ?: '—',
                    $row->numero_activo ?: '—',
                    $row->ip ?: '—',
                    $this->auditLog->snapshotForExport($row->antes),
                    $this->auditLog->snapshotForExport($row->despues),
                ];
            })->all();

            $headers = [
                'Fecha evento',
                'Usuario',
                'Nombre',
                'Módulo',
                'Acción',
                'Tabla',
                'Registro',
                'Número activo',
                'IP',
                'Antes',
                'Después',
            ];

            $timestamp = now()->format('Ymd_His');
            $actorId = (int) $request->session()->get('swafi_user_id');
            $this->auditLog->registerExport(
                format: $format,
                actorId: $actorId,
                ip: $request->ip(),
                filters: $filters,
                rowCount: count($rows)
            );

            if ($format === 'xlsx') {
                $bytes = $this->xlsxExporter->exportBytes('Bitácora SWAFI', $headers, $rows);

                return response($bytes, 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="bitacora_swafi_' . $timestamp . '.xlsx"',
                    'Content-Length' => (string) strlen($bytes),
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }

            if ($format === 'pdf') {
                $bytes = $this->pdfExporter->export(
                    title: 'Bitácora de auditoría SWAFI',
                    headers: $headers,
                    rows: $rows,
                    metadata: [
                        'usuario' => (string) $request->session()->get('swafi_user_name', 'Usuario SWAFI'),
                        'fecha' => now()->format('d/m/Y H:i:s'),
                        'filtros' => $this->auditLog->filterSummary($filters),
                    ]
                );

                return response($bytes, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="bitacora_swafi_' . $timestamp . '.pdf"',
                    'Content-Length' => (string) strlen($bytes),
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }

            return response()->streamDownload(function () use ($headers, $rows): void {
                $output = fopen('php://output', 'wb');

                if ($output === false) {
                    return;
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    fputcsv(
                        $output,
                        array_map(fn ($value) => $this->csvSafeValue($value), $row)
                    );
                }

                fclose($output);
            }, 'bitacora_swafi_' . $timestamp . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (DomainException $exception) {
            return redirect()
                ->route('seguridad', $redirectFilters)
                ->withErrors(['bitacora' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'audit_log_export',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('seguridad', $redirectFilters)
                ->withErrors([
                    'bitacora' => "No fue posible generar la exportación solicitada. Referencia: {$reference}.",
                ]);
        }
    }

    private function csvSafeValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

}
