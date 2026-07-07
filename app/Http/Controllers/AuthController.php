<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\RecaptchaV3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('swafi_autenticado')) {
            return redirect()->route('dashboard');
        }

        if (Auth::check() && Auth::user() instanceof User && $this->isUserActive(Auth::user())) {
            $this->hydrateSwafiSession($request, Auth::user());

            return redirect()->route('dashboard');
        }

        if (Auth::check()) {
            Auth::logout();
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'usuario' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:120'],
            'remember' => ['nullable', 'boolean'],
            'g-recaptcha-response' => ['required', new RecaptchaV3('login')],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
            'g-recaptcha-response.required' => 'No se recibió la validación reCAPTCHA.',
        ]);

        $identity = trim($request->input('usuario'));
        $password = (string) $request->input('password');
        $remember = $request->boolean('remember');

        $this->ensureDefaultAdmin();

        $user = $this->findUserByIdentity($identity);

        if (!$user || !$this->isUserActive($user) || !Hash::check($password, $user->password)) {
            $this->registrarBitacoraLogin(null, 'INICIO_SESION_FALLIDO', $identity, $request->ip());

            return back()
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos, o usuario inactivo.',
                ])
                ->withInput($request->only('usuario', 'remember'));
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $this->hydrateSwafiSession($request, $user);

        $this->updateLastAccess($user->id, $request->ip());
        $this->registrarBitacoraLogin($user->id, 'INICIO_SESION', $identity, $request->ip());

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $userId = $request->session()->get('swafi_user_id');

        $this->registrarBitacoraLogin(
            $userId,
            'CIERRE_SESION',
            $request->session()->get('swafi_usuario'),
            $request->ip()
        );

        Auth::logout();

        $request->session()->forget([
            'swafi_user_id',
            'swafi_usuario',
            'swafi_nombre',
            'swafi_roles',
            'swafi_permissions',
            'swafi_autenticado',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function hydrateSwafiSession(Request $request, User $user): void
    {
        $roles = $this->rolesForUser($user->id);
        $permissions = $this->permissionsForUser($user->id);

        $request->session()->put('swafi_user_id', $user->id);
        $request->session()->put('swafi_usuario', $user->usuario ?: $user->email);
        $request->session()->put('swafi_nombre', $user->name);
        $request->session()->put('swafi_roles', $roles);
        $request->session()->put('swafi_permissions', $permissions);
        $request->session()->put('swafi_autenticado', true);
    }

    private function findUserByIdentity(string $identity): ?User
    {
        return User::query()
            ->where(function ($query) use ($identity) {
                $query->where('email', $identity);

                if (Schema::hasColumn('users', 'usuario')) {
                    $query->orWhere('usuario', $identity);
                }
            })
            ->first();
    }

    private function isUserActive(User $user): bool
    {
        if (!Schema::hasColumn('users', 'estatus')) {
            return true;
        }

        return ($user->estatus ?? 'activo') === 'activo';
    }

    private function ensureDefaultAdmin(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $now = now();

        $query = User::query()
            ->where('email', 'admin.swafi@bimbo.local');

        if (Schema::hasColumn('users', 'usuario')) {
            $query->orWhere('usuario', 'admin.swafi');
        }

        $admin = $query->first();

        $payload = [
            'name' => 'Administrador SWAFI',
            'email' => 'admin.swafi@bimbo.local',
            'email_verified_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('users', 'usuario')) {
            $payload['usuario'] = 'admin.swafi';
        }

        if (Schema::hasColumn('users', 'estatus')) {
            $payload['estatus'] = 'activo';
        }

        if ($admin) {
            DB::table('users')
                ->where('id', $admin->id)
                ->update($payload);

            $adminUserId = $admin->id;
        } else {
            $payload['password'] = Hash::make('12345678');
            $payload['created_at'] = $now;

            $adminUserId = DB::table('users')->insertGetId($payload);
        }

        $this->ensureBaseRolesPermissions((int) $adminUserId);
    }

    private function ensureBaseRolesPermissions(int $adminUserId): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        $roles = [
            [
                'nombre' => 'Administrador SWAFI',
                'descripcion' => 'Administración general, seguridad, catálogos y bitácora.',
            ],
            [
                'nombre' => 'Usuario Captura',
                'descripcion' => 'Registro individual y masivo de expedientes de activo fijo.',
            ],
            [
                'nombre' => 'Usuario Consulta / Auditoría',
                'descripcion' => 'Consulta, reportes, exportación y revisión de trazabilidad.',
            ],
            [
                'nombre' => 'Usuario Planta / Inventarios',
                'descripcion' => 'Consulta y seguimiento de ubicación física e inventarios.',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $role['nombre']],
                [
                    'descripcion' => $role['descripcion'],
                    'activo' => 1,
                    'updated_at' => $now,
                ]
            );
        }

        $permissions = [
            ['clave' => 'dashboard.ver', 'descripcion' => 'Visualizar dashboard principal.'],
            ['clave' => 'expedientes.ver', 'descripcion' => 'Consultar expedientes.'],
            ['clave' => 'expedientes.crear', 'descripcion' => 'Crear expedientes.'],
            ['clave' => 'expedientes.editar', 'descripcion' => 'Editar expedientes.'],
            ['clave' => 'documentos.cargar', 'descripcion' => 'Registrar documentos PDF/XML.'],
            ['clave' => 'valores.administrar', 'descripcion' => 'Administrar valores fiscales y financieros.'],
            ['clave' => 'ubicaciones.administrar', 'descripcion' => 'Administrar ubicación física e inventarios.'],
            ['clave' => 'reportes.exportar', 'descripcion' => 'Exportar consultas y reportes.'],
            ['clave' => 'catalogos.administrar', 'descripcion' => 'Administrar catálogos base.'],
            ['clave' => 'seguridad.administrar', 'descripcion' => 'Administrar usuarios, roles y permisos.'],
            ['clave' => 'bitacora.ver', 'descripcion' => 'Consultar bitácora de auditoría.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $permission['clave']],
                [
                    'descripcion' => $permission['descripcion'],
                    'updated_at' => $now,
                ]
            );
        }

        if (!Schema::hasTable('permission_role') || !Schema::hasTable('role_user')) {
            return;
        }

        $adminRoleId = DB::table('roles')
            ->where('nombre', 'Administrador SWAFI')
            ->value('id');

        if (!$adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('role_user')->updateOrInsert([
            'user_id' => $adminUserId,
            'role_id' => $adminRoleId,
        ]);
    }

    private function rolesForUser(int $userId): array
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('role_user')) {
            return [];
        }

        return DB::table('roles as r')
            ->join('role_user as ru', 'ru.role_id', '=', 'r.id')
            ->where('ru.user_id', $userId)
            ->where('r.activo', 1)
            ->pluck('r.nombre')
            ->all();
    }

    private function permissionsForUser(int $userId): array
    {
        if (
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('permission_role') ||
            !Schema::hasTable('role_user')
        ) {
            return [];
        }

        return DB::table('permissions as p')
            ->join('permission_role as pr', 'pr.permission_id', '=', 'p.id')
            ->join('role_user as ru', 'ru.role_id', '=', 'pr.role_id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $userId)
            ->where('r.activo', 1)
            ->distinct()
            ->pluck('p.clave')
            ->all();
    }

    private function updateLastAccess(int $userId, ?string $ip): void
    {
        $payload = [];

        if (Schema::hasColumn('users', 'ultimo_acceso')) {
            $payload['ultimo_acceso'] = now();
        }

        if (Schema::hasColumn('users', 'ultimo_ip')) {
            $payload['ultimo_ip'] = $ip;
        }

        if (!empty($payload)) {
            $payload['updated_at'] = now();

            DB::table('users')
                ->where('id', $userId)
                ->update($payload);
        }
    }

    private function registrarBitacoraLogin(?int $userId, string $accion, ?string $identity, ?string $ip): void
    {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $userId,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => $accion,
                'tabla_afectada' => 'users',
                'registro_clave' => $userId ? (string) $userId : null,
                'antes' => null,
                'despues' => json_encode(['usuario_intento' => $identity], JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // El inicio o cierre de sesión no debe bloquearse por un error de bitácora.
        }
    }
}
