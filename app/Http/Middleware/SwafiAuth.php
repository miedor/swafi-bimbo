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
    private int $inactiveSeconds = 600;

    public function __construct(
        private readonly SwafiAuthorizationService $authorization
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('swafi_autenticado')) {
            if (Auth::check()) {
                $user = Auth::user();

                if (!$user || !$this->isUserActive((int) $user->id)) {
                    $this->invalidateSession($request);

                    return redirect()
                        ->route('login')
                        ->withErrors([
                            'usuario' => 'La sesión ya no es válida, el usuario fue desactivado o se encuentra bloqueado.',
                        ]);
                }

                $this->hydrateSwafiSession($request, (int) $user->id);
            } else {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'usuario' => 'Debes iniciar sesión para acceder al sistema SWAFI.',
                    ]);
            }
        }

        $userId = (int) $request->session()->get('swafi_user_id');

        if ($userId > 0 && !$this->isUserActive($userId)) {
            $this->invalidateSession($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'usuario' => 'La sesión ya no es válida, el usuario fue desactivado o se encuentra bloqueado.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Permisos siempre vigentes
        |--------------------------------------------------------------------------
        | Se actualizan en cada solicitud protegida. Así, una modificación hecha
        | en Seguridad y acceso surte efecto inmediatamente y no queda una sesión
        | con permisos anteriores.
        */
        if ($userId > 0) {
            $this->authorization->refreshSession($request, $userId);
        }

        if ($this->sessionExpiredByInactivity($request)) {
            $this->registrarSesionExpirada($request);
            $this->invalidateSession($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'usuario' => 'La sesión se cerró automáticamente por 10 minutos de inactividad.',
                ]);
        }

        $routeName = $request->route()?->getName();
        $requiredPermission = $this->requiredPermissionFor($request, $routeName);

        if ($requiredPermission && !$this->can($request, $requiredPermission)) {
            $this->registrarAccesoDenegado($request, $requiredPermission, $routeName);

            return redirect()
                ->route('dashboard')
                ->withErrors([
                    'permisos' => 'Tu usuario no tiene permiso para acceder a esa funcionalidad.',
                ]);
        }

        $request->session()->put('swafi_last_activity_at', now()->timestamp);

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }

    private function requiredPermissionFor(Request $request, ?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        if (in_array($routeName, ['logout', 'perfil', 'perfil.update', 'perfil.avatar', 'perfil.avatar.destroy'], true)) {
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
            'inventario-evidencias.descargar' => 'expedientes.ver',

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

    private function sessionExpiredByInactivity(Request $request): bool
    {
        $lastActivity = (int) $request->session()->get('swafi_last_activity_at', now()->timestamp);

        return (now()->timestamp - $lastActivity) > $this->inactiveSeconds;
    }

    private function hydrateSwafiSession(Request $request, int $userId): void
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return;
        }

        $context = $this->authorization->contextForUser($userId);
        $roles = $context['roles'];
        $permissions = $context['permissions'];

        $request->session()->put('swafi_user_id', $user->id);
        $request->session()->put('swafi_usuario', $user->usuario ?: $user->email);
        $request->session()->put('swafi_nombre', $user->name);
        $request->session()->put('swafi_avatar_path', $user->avatar_path ?? null);
        $request->session()->put('swafi_avatar_disk', $user->avatar_disk ?? null);
        $request->session()->put('swafi_avatar_version', now()->timestamp);
        $request->session()->put('swafi_roles', $roles);
        $request->session()->put('swafi_permissions', $permissions);
        $request->session()->put('swafi_last_activity_at', now()->timestamp);
        $request->session()->put('swafi_autenticado', true);
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
            'swafi_last_activity_at',
            'swafi_autenticado',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function registrarSesionExpirada(Request $request): void
    {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $request->session()->get('swafi_user_id'),
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'CIERRE_SESION_INACTIVIDAD',
                'tabla_afectada' => 'sessions',
                'registro_clave' => $request->session()->getId(),
                'antes' => null,
                'despues' => json_encode([
                    'minutos_inactividad' => 10,
                    'url' => $request->fullUrl(),
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Un error de bitácora no debe bloquear el cierre por inactividad.
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
            // Un error de bitácora no debe bloquear la navegación.
        }
    }
}
