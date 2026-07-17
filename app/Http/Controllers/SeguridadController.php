<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSecurityUserRequest;
use App\Http\Requests\UpdateSecurityUserStatusRequest;
use App\Services\UserAccessManagementService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeguridadController extends Controller
{
    public function __construct(
        private readonly UserAccessManagementService $userAccess
    ) {
    }

    public function index(Request $request)
    {
        $tabActivo = $this->normalizeTab($request->input('tab', 'usuarios'));

        $usuariosQuery = $this->usuariosQuery($request);
        $bitacoraQuery = $this->bitacoraQuery($request);

        if ($tabActivo === 'usuarios' && $request->input('export') === 'csv') {
            return $this->exportUsuariosCsv($usuariosQuery);
        }

        if ($tabActivo === 'roles' && $request->input('export') === 'csv') {
            return $this->exportRolesCsv();
        }

        if ($tabActivo === 'bitacora' && $request->input('export') === 'csv') {
            return $this->exportBitacoraCsv($bitacoraQuery);
        }

        return view('swafi.seguridad', [
            'tabActivo' => $tabActivo,
            'usuarios' => $usuariosQuery
                ->paginate((int) $request->input('per_page', 10), ['*'], 'usuarios_page')
                ->withQueryString(),
            'roles' => $this->rolesWithPermissions(),
            'rolesAsignables' => DB::table('roles')
                ->where('activo', 1)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'activo']),
            'permissions' => DB::table('permissions')
                ->orderBy('clave')
                ->get(),
            'bitacora' => $bitacoraQuery
                ->paginate((int) $request->input('per_page', 10), ['*'], 'bitacora_page')
                ->withQueryString(),
            'usuarioEdit' => $this->findUserForEdit($request->input('editar_usuario')),
            'rolEdit' => $this->findRoleForEdit($request->input('editar_rol')),
            'permisoEdit' => $this->findPermissionForEdit($request->input('editar_permiso')),
            'usuarioRoles' => $this->userRolesForEdit($request->input('editar_usuario')),
            'rolPermisos' => $this->rolePermissionsForEdit($request->input('editar_rol')),
            'filtros' => $request->all(),
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

    public function storeRole(Request $request)
    {
        $id = $request->input('id');

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:roles,id'],
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', Rule::in(['1', '0'])],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], $this->messages());

        $permissionIds = array_values(array_unique($validated['permission_ids'] ?? []));
        unset($validated['permission_ids']);

        DB::transaction(function () use ($validated, $permissionIds, $id) {
            $before = $id
                ? DB::table('roles')->where('id', $id)->first()
                : null;

            $now = now();

            $payload = [
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'activo' => (int) $validated['activo'],
                'updated_at' => $now,
            ];

            if ($id) {
                DB::table('roles')
                    ->where('id', $id)
                    ->update($payload);

                $roleId = (int) $id;
                $accion = 'SEGURIDAD_ROL_ACTUALIZACION';
            } else {
                $payload['created_at'] = $now;
                $roleId = (int) DB::table('roles')->insertGetId($payload);
                $accion = 'SEGURIDAD_ROL_ALTA';
            }

            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }

            $after = DB::table('roles')
                ->where('id', $roleId)
                ->first();

            $this->registrarBitacora(
                accion: $accion,
                tablaAfectada: 'roles',
                registroClave: (string) $roleId,
                antes: $before ? (array) $before : null,
                despues: [
                    'rol' => $after ? (array) $after : null,
                    'permisos_asignados' => $permissionIds,
                ]
            );
        });

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with('success', $id ? 'El rol se actualizó correctamente.' : 'El rol se creó correctamente.');
    }

    public function destroyRole(int $role)
    {
        $before = DB::table('roles')
            ->where('id', $role)
            ->first();

        if (!$before) {
            return redirect()
                ->route('seguridad', ['tab' => 'roles'])
                ->withErrors([
                    'rol' => 'El rol seleccionado no existe.',
                ]);
        }

        DB::transaction(function () use ($role, $before) {
            DB::table('roles')
                ->where('id', $role)
                ->update([
                    'activo' => 0,
                    'updated_at' => now(),
                ]);

            $after = DB::table('roles')
                ->where('id', $role)
                ->first();

            $this->registrarBitacora(
                accion: 'SEGURIDAD_ROL_DESACTIVACION',
                tablaAfectada: 'roles',
                registroClave: (string) $role,
                antes: (array) $before,
                despues: $after ? (array) $after : null
            );
        });

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with('success', 'El rol fue desactivado correctamente.');
    }

    public function storePermission(Request $request)
    {
        $id = $request->input('id');

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:permissions,id'],
            'clave' => ['required', 'string', 'max:80', Rule::unique('permissions', 'clave')->ignore($id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], $this->messages());

        DB::transaction(function () use ($validated, $id) {
            $before = $id
                ? DB::table('permissions')->where('id', $id)->first()
                : null;

            $now = now();

            if ($id) {
                DB::table('permissions')
                    ->where('id', $id)
                    ->update([
                        'clave' => $validated['clave'],
                        'descripcion' => $validated['descripcion'] ?? null,
                        'updated_at' => $now,
                    ]);

                $permissionId = (int) $id;
                $accion = 'SEGURIDAD_PERMISO_ACTUALIZACION';
            } else {
                $permissionId = (int) DB::table('permissions')->insertGetId([
                    'clave' => $validated['clave'],
                    'descripcion' => $validated['descripcion'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $accion = 'SEGURIDAD_PERMISO_ALTA';
            }

            $after = DB::table('permissions')
                ->where('id', $permissionId)
                ->first();

            $this->registrarBitacora(
                accion: $accion,
                tablaAfectada: 'permissions',
                registroClave: (string) $permissionId,
                antes: $before ? (array) $before : null,
                despues: $after ? (array) $after : null
            );
        });

        return redirect()
            ->route('seguridad', ['tab' => 'roles'])
            ->with('success', $id ? 'El permiso se actualizó correctamente.' : 'El permiso se creó correctamente.');
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
            ->select([
                'r.id',
                'r.nombre',
                'r.descripcion',
                'r.activo',
                'r.created_at',
                'r.updated_at',
                DB::raw("COALESCE(GROUP_CONCAT(p.clave ORDER BY p.clave SEPARATOR ', '), 'Sin permisos') as permisos"),
                DB::raw('COUNT(p.id) as total_permisos'),
            ])
            ->groupBy(
                'r.id',
                'r.nombre',
                'r.descripcion',
                'r.activo',
                'r.created_at',
                'r.updated_at'
            )
            ->orderBy('r.nombre')
            ->get();
    }

    private function bitacoraQuery(Request $request)
    {
        $query = DB::table('bitacora_auditoria as b')
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
                'u.usuario as usuario',
                'u.name as usuario_nombre',
                'u.email as usuario_email',
            ]);

        if ($request->filled('buscar_bitacora')) {
            $buscar = '%' . trim($request->input('buscar_bitacora')) . '%';

            $query->where(function ($where) use ($buscar) {
                $where->where('b.modulo', 'like', $buscar)
                    ->orWhere('b.accion', 'like', $buscar)
                    ->orWhere('b.tabla_afectada', 'like', $buscar)
                    ->orWhere('b.registro_clave', 'like', $buscar)
                    ->orWhere('u.usuario', 'like', $buscar)
                    ->orWhere('u.name', 'like', $buscar)
                    ->orWhere('u.email', 'like', $buscar);
            });
        }

        if ($request->filled('modulo')) {
            $query->where('b.modulo', 'like', '%' . $request->input('modulo') . '%');
        }

        if ($request->filled('accion')) {
            $query->where('b.accion', 'like', '%' . $request->input('accion') . '%');
        }

        if ($request->filled('numero_activo')) {
            $query->where('b.numero_activo', 'like', '%' . $request->input('numero_activo') . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('b.fecha_evento', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('b.fecha_evento', '<=', $request->input('fecha_hasta'));
        }

        return $query
            ->orderByDesc('b.fecha_evento')
            ->orderByDesc('b.id');
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
            'permisos_total' => DB::table('permissions')->count(),
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
                'Activo',
                'Total permisos',
                'Permisos',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->nombre,
                    $row->descripcion,
                    ((int) $row->activo) === 1 ? 'Sí' : 'No',
                    $row->total_permisos,
                    $row->permisos,
                ]);
            }

            fclose($output);
        }, 'seguridad_roles_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportBitacoraCsv($query)
    {
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Fecha evento',
                'Usuario',
                'Módulo',
                'Acción',
                'Tabla',
                'Registro',
                'Número activo',
                'IP',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->fecha_evento,
                    $row->usuario ?: $row->usuario_email,
                    $row->modulo,
                    $row->accion,
                    $row->tabla_afectada,
                    $row->registro_clave,
                    $row->numero_activo,
                    $row->ip,
                ]);
            }

            fclose($output);
        }, 'bitacora_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'unique' => 'El valor capturado en :attribute ya existe.',
            'email' => 'El correo electrónico no tiene un formato válido.',
            'exists' => 'El valor seleccionado en :attribute no existe.',
            'min' => 'El campo :attribute no cumple la longitud mínima.',
            'max' => 'El campo :attribute supera la longitud permitida.',
            'in' => 'El campo :attribute contiene un valor no válido.',
        ];
    }

    private function registrarBitacora(
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => session('swafi_user_id'),
            'modulo' => 'M04 Administración y seguridad del sistema',
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
}
