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
    private int $maxFailedAttempts = 5;

    public function showLogin(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | No reanudar sesiones incompletas o persistentes
        |--------------------------------------------------------------------------
        | SWAFI exige que existan simultáneamente la autenticación de Laravel y
        | el contexto de seguridad propio. Si solo existe una de las dos partes,
        | la sesión se invalida y la persona debe autenticarse nuevamente.
        */
        if (
            $request->session()->get('swafi_autenticado') === true &&
            Auth::check() &&
            Auth::user() instanceof User &&
            $this->isUserActive(Auth::user())
        ) {
            return redirect()->route('dashboard');
        }

        if (Auth::check() || $request->session()->has('swafi_autenticado')) {
            $this->invalidateAuthentication($request);
        }

        return view('auth.login', [
            'sessionNotice' => $this->sessionNotice((string) $request->query('motivo', '')),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'usuario' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:120'],
            'g-recaptcha-response' => ['required', new RecaptchaV3('login')],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
            'g-recaptcha-response.required' => 'No se recibió la validación reCAPTCHA.',
        ]);

        $identity = trim($request->input('usuario'));
        $password = (string) $request->input('password');

        $user = $this->findUserByIdentity($identity);

        if (!$user) {
            $this->registrarBitacoraLogin(null, 'INICIO_SESION_FALLIDO', $identity, $request->ip(), [
                'motivo' => 'Usuario no encontrado.',
            ]);

            return back()
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos.',
                ])
                ->withInput($request->only('usuario'));
        }

        if ($this->isUserBlocked($user)) {
            $this->registrarBitacoraLogin($user->id, 'INICIO_SESION_BLOQUEADO', $identity, $request->ip(), [
                'motivo' => 'Usuario bloqueado por intentos fallidos.',
                'intentos_fallidos' => $user->intentos_fallidos ?? null,
            ]);

            return back()
                ->withErrors([
                    'usuario' => 'El usuario se encuentra bloqueado por intentos fallidos. Solicita al Administrador SWAFI restablecer la contraseña y activar la cuenta.',
                ])
                ->withInput($request->only('usuario'));
        }

        if (!$this->isUserActive($user)) {
            $this->registrarBitacoraLogin($user->id, 'INICIO_SESION_INACTIVO', $identity, $request->ip(), [
                'estatus' => $user->estatus ?? null,
            ]);

            return back()
                ->withErrors([
                    'usuario' => 'El usuario se encuentra inactivo. Solicita revisión al Administrador SWAFI.',
                ])
                ->withInput($request->only('usuario'));
        }

        if (!Hash::check($password, $user->password)) {
            $bloqueado = $this->registerFailedAttempt($user, $identity, $request->ip());

            if ($bloqueado) {
                return back()
                    ->withErrors([
                        'usuario' => 'Contraseña incorrecta. El usuario quedó bloqueado por superar 5 intentos fallidos. Solo el Administrador SWAFI puede restablecer la contraseña y activar la cuenta.',
                    ])
                    ->withInput($request->only('usuario'));
            }

            $attempts = $this->failedAttemptsFor($user->id);
            $remaining = max($this->maxFailedAttempts - $attempts, 0);

            return back()
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos. Intentos restantes antes del bloqueo: ' . $remaining . '.',
                ])
                ->withInput($request->only('usuario'));
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        $this->resetFailedAttempts($user->id);
        $this->hydrateSwafiSession($request, $user->fresh());

        $this->updateLastAccess($user->id, $request->ip());
        $this->registrarBitacoraLogin($user->id, 'INICIO_SESION', $identity, $request->ip(), [
            'sesion_persistente' => false,
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $userId = $request->session()->get('swafi_user_id');
        $reason = $this->normalizeLogoutReason((string) $request->input('motivo', 'manual'));
        $action = match ($reason) {
            'inactividad' => 'CIERRE_SESION_INACTIVIDAD',
            'navegacion_atras' => 'CIERRE_SESION_NAVEGACION_ATRAS',
            'cache_restaurada' => 'CIERRE_SESION_CACHE_RESTAURADA',
            'sesion_invalida' => 'CIERRE_SESION_INVALIDA',
            default => 'CIERRE_SESION',
        };

        $this->registrarBitacoraLogin(
            $userId,
            $action,
            $request->session()->get('swafi_usuario'),
            $request->ip(),
            [
                'motivo' => $reason,
                'session_id' => $request->session()->getId(),
                'url_anterior' => $request->headers->get('referer'),
            ]
        );

        $this->invalidateAuthentication($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json([
                    'ok' => true,
                    'redirect' => route('login', ['motivo' => $reason]),
                ])
                ->withHeaders([
                    'Clear-Site-Data' => '"cache", "storage"',
                ]);
        }

        return redirect()
            ->route('login', ['motivo' => $reason])
            ->withHeaders([
                'Clear-Site-Data' => '"cache", "storage"',
            ]);
    }

    public function heartbeat(Request $request)
    {
        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'inactivity_seconds' => (int) config('session.swafi_inactivity_seconds', 600),
            'absolute_seconds' => (int) config('session.swafi_absolute_seconds', 28800),
        ]);
    }

    private function hydrateSwafiSession(Request $request, User $user): void
    {
        $roles = $this->rolesForUser($user->id);
        $permissions = $this->permissionsForUser($user->id);

        $request->session()->put('swafi_user_id', $user->id);
        $request->session()->put('swafi_usuario', $user->usuario ?: $user->email);
        $request->session()->put('swafi_nombre', $user->name);
        $request->session()->put('swafi_avatar_path', $user->avatar_path ?? null);
        $request->session()->put('swafi_avatar_disk', $user->avatar_disk ?? null);
        $request->session()->put('swafi_avatar_version', now()->timestamp);
        $request->session()->put('swafi_roles', $roles);
        $request->session()->put('swafi_permissions', $permissions);
        $request->session()->put('swafi_session_started_at', now()->timestamp);
        $request->session()->put('swafi_last_activity_at', now()->timestamp);
        $request->session()->put('swafi_session_fingerprint', $this->sessionFingerprint($request));
        $request->session()->put('swafi_session_id', $request->session()->getId());
        $request->session()->put('swafi_autenticado', true);
    }

    private function invalidateAuthentication(Request $request): void
    {
        Auth::logout();

        $request->session()->forget([
            'swafi_user_id',
            'swafi_usuario',
            'swafi_nombre',
            'swafi_avatar_path',
            'swafi_avatar_disk',
            'swafi_avatar_version',
            'swafi_roles',
            'swafi_permissions',
            'swafi_session_started_at',
            'swafi_last_activity_at',
            'swafi_session_fingerprint',
            'swafi_session_id',
            'swafi_autenticado',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function sessionFingerprint(Request $request): string
    {
        $userAgent = trim((string) $request->userAgent());
        $applicationKey = (string) config('app.key', 'swafi-session-key');

        return hash_hmac('sha256', $userAgent, $applicationKey);
    }

    private function normalizeLogoutReason(string $reason): string
    {
        return in_array($reason, [
            'manual',
            'inactividad',
            'navegacion_atras',
            'cache_restaurada',
            'sesion_invalida',
        ], true) ? $reason : 'manual';
    }

    private function sessionNotice(string $reason): ?string
    {
        return match ($reason) {
            'manual' => 'La sesión se cerró correctamente.',
            'inactividad' => 'La sesión se cerró automáticamente por inactividad. Inicia sesión nuevamente.',
            'navegacion_atras' => 'Por seguridad, SWAFI cerró la sesión al utilizar el botón Atrás del navegador.',
            'cache_restaurada' => 'La pantalla anterior pertenecía a una sesión cerrada. Inicia sesión nuevamente.',
            'sesion_invalida' => 'La sesión dejó de ser válida. Inicia sesión nuevamente.',
            default => null,
        };
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

    private function isUserBlocked(User $user): bool
    {
        if (!Schema::hasColumn('users', 'estatus')) {
            return false;
        }

        return ($user->estatus ?? 'activo') === 'bloqueado';
    }

    private function registerFailedAttempt(User $user, string $identity, ?string $ip): bool
    {
        $currentAttempts = Schema::hasColumn('users', 'intentos_fallidos')
            ? (int) ($user->intentos_fallidos ?? 0)
            : 0;

        $attempts = $currentAttempts + 1;
        $payload = ['updated_at' => now()];

        if (Schema::hasColumn('users', 'intentos_fallidos')) {
            $payload['intentos_fallidos'] = $attempts;
        }

        if (Schema::hasColumn('users', 'ultimo_intento_fallido')) {
            $payload['ultimo_intento_fallido'] = now();
        }

        $blocked = $attempts >= $this->maxFailedAttempts;

        if ($blocked && Schema::hasColumn('users', 'estatus')) {
            $payload['estatus'] = 'bloqueado';
        }

        if ($blocked && Schema::hasColumn('users', 'bloqueado_en')) {
            $payload['bloqueado_en'] = now();
        }

        if ($blocked && Schema::hasColumn('users', 'bloqueado_motivo')) {
            $payload['bloqueado_motivo'] = 'Bloqueo automático por 5 intentos fallidos de inicio de sesión.';
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update($payload);

        $this->registrarBitacoraLogin($user->id, 'INICIO_SESION_FALLIDO', $identity, $ip, [
            'intentos_fallidos' => $attempts,
            'bloqueado' => $blocked,
        ]);

        if ($blocked) {
            $this->registrarBitacoraLogin($user->id, 'USUARIO_BLOQUEADO_INTENTOS', $identity, $ip, [
                'intentos_fallidos' => $attempts,
                'motivo' => '5 intentos fallidos.',
            ]);
        }

        return $blocked;
    }

    private function failedAttemptsFor(int $userId): int
    {
        if (!Schema::hasColumn('users', 'intentos_fallidos')) {
            return 0;
        }

        return (int) DB::table('users')
            ->where('id', $userId)
            ->value('intentos_fallidos');
    }

    private function resetFailedAttempts(int $userId): void
    {
        $payload = [];

        if (Schema::hasColumn('users', 'intentos_fallidos')) {
            $payload['intentos_fallidos'] = 0;
        }

        if (Schema::hasColumn('users', 'ultimo_intento_fallido')) {
            $payload['ultimo_intento_fallido'] = null;
        }

        if (Schema::hasColumn('users', 'bloqueado_en')) {
            $payload['bloqueado_en'] = null;
        }

        if (Schema::hasColumn('users', 'bloqueado_motivo')) {
            $payload['bloqueado_motivo'] = null;
        }

        if (!empty($payload)) {
            $payload['updated_at'] = now();

            DB::table('users')
                ->where('id', $userId)
                ->update($payload);
        }
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
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role') || !Schema::hasTable('role_user')) {
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

    private function registrarBitacoraLogin(?int $userId, string $accion, ?string $identity, ?string $ip, array $detalle = []): void
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
                'despues' => json_encode(array_merge(['usuario_intento' => $identity], $detalle), JSON_UNESCAPED_UNICODE),
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
