<?php

namespace App\Http\Middleware;

use App\Services\SwafiAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SwafiAuth
{
    public function __construct(
        private readonly SwafiAuthorizationService $authorization
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Autenticación estricta
        |--------------------------------------------------------------------------
        | No se reconstruye una sesión SWAFI solamente porque exista una cookie de
        | autenticación de Laravel. Deben existir Auth y el contexto SWAFI. Esto
        | evita reingresos por cookies persistentes o páginas restauradas en caché.
        */
        if (
            !Auth::check() ||
            $request->session()->get('swafi_autenticado') !== true
        ) {
            $this->invalidateSession($request);

            return $this->authenticationFailure(
                $request,
                'Debes iniciar sesión para acceder al sistema SWAFI.'
            );
        }

        $userId = (int) $request->session()->get('swafi_user_id');
        $authenticatedUserId = (int) (Auth::id() ?? 0);

        if ($userId <= 0 || $userId !== $authenticatedUserId) {
            $this->registerSecurityClosure($request, 'CIERRE_SESION_IDENTIDAD_INVALIDA', [
                'swafi_user_id' => $userId,
                'auth_user_id' => $authenticatedUserId,
            ]);
            $this->invalidateSession($request);

            return $this->authenticationFailure(
                $request,
                'La identidad de la sesión dejó de ser válida. Inicia sesión nuevamente.'
            );
        }

        if (!$this->isUserActive($userId)) {
            $this->registerSecurityClosure($request, 'CIERRE_SESION_USUARIO_INACTIVO');
            $this->invalidateSession($request);

            return $this->authenticationFailure(
                $request,
                'La sesión ya no es válida porque el usuario fue desactivado o bloqueado.'
            );
        }

        $invalidReason = $this->invalidSessionReason($request);

        if ($invalidReason !== null) {
            $action = match ($invalidReason) {
                'inactividad' => 'CIERRE_SESION_INACTIVIDAD',
                'duracion_absoluta' => 'CIERRE_SESION_DURACION_ABSOLUTA',
                'huella_invalida' => 'CIERRE_SESION_HUELLA_INVALIDA',
                default => 'CIERRE_SESION_INVALIDA',
            };

            $this->registerSecurityClosure($request, $action, [
                'motivo' => $invalidReason,
                'url' => $request->fullUrl(),
            ]);
            $this->invalidateSession($request);

            $message = match ($invalidReason) {
                'inactividad' => 'La sesión se cerró automáticamente por 10 minutos de inactividad.',
                'duracion_absoluta' => 'La sesión alcanzó su duración máxima de seguridad. Inicia sesión nuevamente.',
                'huella_invalida' => 'La sesión cambió de navegador o dispositivo y fue cerrada por seguridad.',
                default => 'La sesión dejó de ser válida. Inicia sesión nuevamente.',
            };

            return $this->authenticationFailure($request, $message);
        }

        /*
        |--------------------------------------------------------------------------
        | Permisos siempre vigentes
        |--------------------------------------------------------------------------
        | Una modificación de rol o permiso se refleja en la siguiente solicitud.
        */
        $this->authorization->refreshSession($request, $userId);

        $routeName = $request->route()?->getName();
        $requiredPermission = $this->requiredPermissionFor($request, $routeName);

        if ($requiredPermission && !$this->can($request, $requiredPermission)) {
            $this->registrarAccesoDenegado($request, $requiredPermission, $routeName);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu usuario no tiene permiso para acceder a esa funcionalidad.',
                ], 403);
            }

            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'permisos' => 'Tu usuario no tiene permiso para acceder a esa funcionalidad.',
                ]);
        }

        $request->session()->put('swafi_last_activity_at', now()->timestamp);

        return $next($request);
    }

    private function requiredPermissionFor(Request $request, ?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        if (in_array($routeName, [
            'session.heartbeat',
            'perfil',
            'perfil.update',
            'perfil.avatar',
            'perfil.avatar.destroy',
        ], true)) {
            return null;
        }

        if ($routeName === 'seguridad') {
            $tab = (string) $request->input('tab', 'usuarios');

            return $tab === 'bitacora'
                ? 'bitacora.ver'
                : 'seguridad.administrar';
        }

        return match ($routeName) {
            'dashboard' => 'dashboard.ver',

            'registro-individual',
            'registro-individual.store',
            'registro-masivo',
            'registro-masivo.importar',
            'registro-masivo.aplicar',
            'registro-masivo.cancelar',
            'registro-masivo.incidencias',
            'registro-masivo.incidencias-csv',
            'registro-masivo.plantilla' => 'expedientes.crear',

            'busqueda',
            'busquedas-guardadas.store',
            'busquedas-guardadas.apply',
            'busquedas-guardadas.destroy',
            'expediente',
            'documentos.ver',
            'documentos.descargar',
            'documentos.descargar-todos',
            'inventario-evidencias.ver',
            'inventario-evidencias.descargar',
            'activos.etiqueta',
            'activos.etiqueta.auditar' => 'expedientes.ver',

            'expedientes.editar',
            'expedientes.actualizar',
            'expedientes.eliminar' => 'expedientes.editar',

            'documentos.store',
            'documentos.eliminar' => 'documentos.cargar',

            'observaciones.store' => 'observaciones.crear',

            'observaciones.tomar',
            'observaciones.atender' => 'observaciones.atender',

            'observaciones.validar',
            'observaciones.cancelar' => 'observaciones.validar',

            'valores' => 'valores.ver',

            'valores.store',
            'valores.plantilla',
            'valores.importar',
            'valores.destroy' => 'valores.administrar',

            'cfdi.revalidar' => 'cfdi.validar',

            'ubicacion',
            'ubicacion.movimiento',
            'ubicacion.inventario',
            'inventario-evidencias.eliminar' => 'ubicaciones.administrar',

            'reportes' => 'reportes.exportar',

            'reportes-guardados.store',
            'reportes-guardados.apply',
            'reportes-guardados.destroy' => 'reportes.plantillas',

            'catalogos',
            'catalogos.store',
            'catalogos.importar',
            'catalogos.plantilla',
            'catalogos.destroy' => 'catalogos.administrar',

            'seguridad.usuarios.store',
            'seguridad.usuarios.destroy',
            'seguridad.roles.store',
            'seguridad.roles.destroy',
            'seguridad.permisos.store' => 'seguridad.administrar',

            default => null,
        };
    }

    private function can(Request $request, string $permission): bool
    {
        return $this->authorization->canFromSession($request, $permission);
    }

    private function invalidSessionReason(Request $request): ?string
    {
        $expectedFingerprint = (string) $request->session()->get('swafi_session_fingerprint', '');
        $currentFingerprint = $this->sessionFingerprint($request);

        if (
            $expectedFingerprint === '' ||
            !hash_equals($expectedFingerprint, $currentFingerprint)
        ) {
            return 'huella_invalida';
        }

        $now = now()->timestamp;
        $startedAt = (int) $request->session()->get('swafi_session_started_at', 0);
        $lastActivityAt = (int) $request->session()->get('swafi_last_activity_at', 0);
        $absoluteSeconds = max((int) config('session.swafi_absolute_seconds', 28800), 60);
        $inactiveSeconds = max((int) config('session.swafi_inactivity_seconds', 600), 60);

        if ($startedAt <= 0 || ($now - $startedAt) > $absoluteSeconds) {
            return 'duracion_absoluta';
        }

        if ($lastActivityAt <= 0 || ($now - $lastActivityAt) > $inactiveSeconds) {
            return 'inactividad';
        }

        return null;
    }

    private function sessionFingerprint(Request $request): string
    {
        $userAgent = trim((string) $request->userAgent());
        $applicationKey = (string) config('app.key', 'swafi-session-key');

        return hash_hmac('sha256', $userAgent, $applicationKey);
    }

    private function isUserActive(int $userId): bool
    {
        if (!Schema::hasTable('users')) {
            return false;
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return false;
        }

        return ($user->estatus ?? 'activo') === 'activo';
    }

    private function invalidateSession(Request $request): void
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

    private function authenticationFailure(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => route('login', ['motivo' => 'sesion_invalida']),
            ], 401);
        }

        return redirect()
            ->route('login', ['motivo' => 'sesion_invalida'])
            ->withErrors([
                'usuario' => $message,
            ]);
    }

    private function registerSecurityClosure(Request $request, string $action, array $detail = []): void
    {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $request->session()->get('swafi_user_id'),
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => $action,
                'tabla_afectada' => 'sessions',
                'registro_clave' => $request->session()->getId(),
                'antes' => null,
                'despues' => json_encode(array_merge([
                    'url' => $request->fullUrl(),
                    'user_agent' => $request->userAgent(),
                ], $detail), JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La bitácora no debe impedir el cierre de una sesión inválida.
        }
    }

    private function registrarAccesoDenegado(Request $request, string $permission, ?string $routeName): void
    {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $request->session()->get('swafi_user_id'),
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'ACCESO_DENEGADO',
                'tabla_afectada' => 'routes',
                'registro_clave' => $routeName,
                'antes' => null,
                'despues' => json_encode([
                    'permiso_requerido' => $permission,
                    'url' => $request->fullUrl(),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La bitácora no debe bloquear la navegación.
        }
    }
}
