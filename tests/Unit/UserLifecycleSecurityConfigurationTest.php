<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserLifecycleSecurityConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_user_form_request_requires_an_active_role_and_server_side_validation(): void
    {
        $request = $this->read('app/Http/Requests/StoreSecurityUserRequest.php');

        foreach ([
            "'role_ids' => ['required', 'array', 'min:1']",
            "Rule::exists('roles', 'id')->where",
            "Rule::unique('users', 'usuario')->ignore",
            "Rule::unique('users', 'email')->ignore",
            "new SwafiPasswordPolicy()",
            "'estatus' => ['required', Rule::in(['activo', 'inactivo', 'bloqueado'])]",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_user_status_request_allows_only_activation_or_deactivation(): void
    {
        $request = $this->read('app/Http/Requests/UpdateSecurityUserStatusRequest.php');

        self::assertStringContainsString("Rule::in(['activo', 'inactivo'])", $request);
        self::assertStringContainsString("'motivo' => ['nullable', 'string', 'max:500']", $request);
        self::assertStringContainsString("permissions->contains('seguridad.administrar')", $request);
    }

    public function test_user_management_is_transactional_locked_and_audited(): void
    {
        $service = $this->read('app/Services/UserAccessManagementService.php');

        foreach ([
            'DB::transaction(',
            '->lockForUpdate()',
            'Hash::make(',
            "DB::table('role_user')->insertOrIgnore",
            "DB::table('bitacora_auditoria')->insert",
            "'SEGURIDAD_USUARIO_ALTA'",
            "'SEGURIDAD_USUARIO_ACTUALIZACION'",
            "'SEGURIDAD_USUARIO_ACTIVACION'",
            "'SEGURIDAD_USUARIO_DESACTIVACION'",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_multiple_administrators_are_supported_but_the_last_active_one_is_protected(): void
    {
        $service = $this->read('app/Services/UserAccessManagementService.php');

        self::assertStringContainsString("private const ADMIN_ROLE = 'Administrador SWAFI';", $service);
        self::assertStringContainsString("->where('u.id', '<>', $targetUserId)", $service);
        self::assertStringContainsString('La operación dejaría a SWAFI sin un Administrador activo.', $service);
        self::assertStringContainsString('Asigna primero el rol a otra cuenta.', $service);
    }

    public function test_self_deactivation_self_role_removal_and_self_password_change_are_blocked(): void
    {
        $service = $this->read('app/Services/UserAccessManagementService.php');

        self::assertStringContainsString('No puedes desactivar o bloquear el usuario con el que tienes la sesión actual.', $service);
        self::assertStringContainsString('No puedes retirar de tu propia cuenta el rol Administrador SWAFI.', $service);
        self::assertStringContainsString('Para cambiar tu propia contraseña utiliza la opción Perfil', $service);
    }

    public function test_authorization_changes_and_deactivation_revoke_existing_sessions(): void
    {
        $service = $this->read('app/Services/UserAccessManagementService.php');

        self::assertStringContainsString("DB::table('sessions')->where('user_id', $userId)->delete();", $service);
        self::assertStringContainsString("DB::table('users')->where('id', $userId)->update(['remember_token' => null]);", $service);
        self::assertStringContainsString('$authorizationChanged || $passwordChanged', $service);
    }

    public function test_routes_and_middleware_protect_the_activation_endpoint(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString("->name('seguridad.usuarios.activate')", $routes);
        self::assertStringContainsString("'seguridad.usuarios.activate'", $middleware);
        self::assertStringContainsString("'seguridad.administrar'", $middleware);
    }

    public function test_user_interface_exposes_clear_activate_deactivate_and_role_controls(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');

        self::assertStringContainsString('@foreach ($rolesAsignables as $rol)', $view);
        self::assertStringContainsString('Selecciona al menos un rol activo.', $view);
        self::assertStringContainsString("route('seguridad.usuarios.activate'", $view);
        self::assertStringContainsString('Sus sesiones activas serán revocadas.', $view);
        self::assertStringContainsString('Editar para desbloquear', $view);
    }

    public function test_audit_actions_fit_the_existing_database_limit(): void
    {
        foreach ([
            'SEGURIDAD_USUARIO_ALTA',
            'SEGURIDAD_USUARIO_ACTUALIZACION',
            'SEGURIDAD_USUARIO_ACTIVACION',
            'SEGURIDAD_USUARIO_DESACTIVACION',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action.' supera VARCHAR(40).');
        }
    }

    public function test_existing_captcha_session_and_password_recovery_controls_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $reset = $this->read('app/Http/Controllers/PasswordResetController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $sessionConfig = $this->read('config/session.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString('$request->session()->regenerate();', $auth);
        self::assertStringContainsString('new SwafiPasswordPolicy()', $reset);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("'same_site' => env('SESSION_SAME_SITE', 'strict')", $sessionConfig);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
