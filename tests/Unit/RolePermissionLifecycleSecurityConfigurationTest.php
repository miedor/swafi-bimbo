<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RolePermissionLifecycleSecurityConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_adds_role_and_permission_lifecycle_fields(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_17_000470_add_lifecycle_controls_to_roles_and_permissions.php'
        );

        foreach ([
            "Schema::hasColumn('roles', 'es_sistema')",
            "Schema::hasColumn('permissions', 'activo')",
            "Schema::hasColumn('permissions', 'es_sistema')",
            "roles_activo_sistema_index",
            "permissions_activo_index",
            "permissions_sistema_index",
            "'HU-091,HU-092'",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString('public function down(): void', $migration);
        self::assertStringContainsString("dropColumn('es_sistema')", $migration);
        self::assertStringContainsString("dropColumn('activo')", $migration);
    }

    public function test_base_roles_and_system_permissions_are_marked_as_protected(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_17_000470_add_lifecycle_controls_to_roles_and_permissions.php'
        );

        foreach ([
            'Administrador SWAFI',
            'Usuario Captura',
            'Usuario Consulta / Auditoría',
            'Usuario Planta / Inventarios',
            'seguridad.administrar',
            'ubicaciones.aprobar_traslados',
            'expedientes.revertir_importacion',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString("'es_sistema' => 1", $migration);
        self::assertStringContainsString("'activo' => 1", $migration);
    }

    public function test_role_request_requires_active_permissions_and_server_side_authorization(): void
    {
        $request = $this->read('app/Http/Requests/StoreSecurityRoleRequest.php');
        $baseRequest = $this->read('app/Http/Requests/SecurityAdministrationRequest.php');

        foreach ([
            '\'permission_ids\' => $permissionRule',
            "Rule::exists('permissions', 'id')->where",
            'fn ($query) => $query->where(\'activo\', 1)',
            "Rule::unique('roles', 'nombre')->ignore",
            "normalizedIntegerArray('permission_ids')",
            "'regex:/^[\\pL\\pN][\\pL\\pN ._\\/-]*$/u'",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }

        self::assertStringContainsString('$permissions->contains(\'seguridad.administrar\')', $baseRequest);
        self::assertStringContainsString('$roles->contains(\'administrador swafi\')', $baseRequest);
        self::assertStringContainsString('return $item;', $baseRequest);
    }

    public function test_permission_request_enforces_a_stable_module_action_key(): void
    {
        $request = $this->read('app/Http/Requests/StoreSecurityPermissionRequest.php');

        foreach ([
            "'max:80'",
            "'regex:/^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$/'",
            "Rule::unique('permissions', 'clave')->ignore",
            'La clave debe utilizar el formato modulo.accion',
            "'descripcion' => ['required', 'string', 'min:10', 'max:255']",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_role_and_permission_changes_are_transactional_locked_and_audited(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');

        foreach ([
            'DB::transaction(',
            '->lockForUpdate()',
            "DB::table('bitacora_auditoria')->insert",
            "'SEGURIDAD_ROL_ALTA'",
            "'SEGURIDAD_ROL_ACTUALIZACION'",
            "'SEGURIDAD_ROL_ACTIVACION'",
            "'SEGURIDAD_ROL_DESACTIVACION'",
            "'SEGURIDAD_PERMISO_ALTA'",
            "'SEGURIDAD_PERMISO_ACTUALIZACION'",
            "'SEGURIDAD_PERMISO_ACTIVACION'",
            "'SEGURIDAD_PERMISO_DESACTIVACION'",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_system_roles_cannot_be_renamed_or_deactivated(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');

        self::assertStringContainsString('Los roles base del sistema no pueden activarse, desactivarse ni renombrarse', $service);
        self::assertStringContainsString('El nombre de un rol base del sistema no puede modificarse', $service);
        self::assertStringContainsString('Los roles base del sistema deben permanecer activos.', $service);
        self::assertStringContainsString("private const ADMIN_ROLE = 'Administrador SWAFI';", $service);
    }

    public function test_roles_in_use_and_roles_without_permissions_cannot_be_activated_or_deactivated_unsafely(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');

        self::assertStringContainsString("DB::table('role_user')", $service);
        self::assertStringContainsString('No puedes desactivar un rol asignado a usuarios.', $service);
        self::assertStringContainsString('No puedes activar un rol sin permisos activos.', $service);
        self::assertStringContainsString('Un rol activo debe conservar al menos un permiso activo.', $service);
    }

    public function test_permission_keys_are_immutable_and_system_permissions_cannot_be_deactivated(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');

        self::assertStringContainsString('La clave técnica de un permiso no puede modificarse después de su creación.', $service);
        self::assertStringContainsString('Los permisos base del sistema no pueden activarse, desactivarse ni cambiar su clave técnica.', $service);
        self::assertStringContainsString('No puedes desactivar un permiso asignado a roles.', $service);
    }

    public function test_administrator_receives_only_active_permissions_and_new_permissions_are_attached_automatically(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');
        $authorization = $this->read('app/Services/SwafiAuthorizationService.php');
        $provisioning = $this->read('app/Services/SecureAdministratorProvisioningService.php');

        self::assertStringContainsString('attachPermissionToAdministrator', $service);
        self::assertStringContainsString("->where('activo', 1)", $service);
        self::assertStringContainsString("->where('p.activo', 1)", $authorization);
        self::assertStringContainsString("DB::table('permissions')\n                ->where('activo', 1)", $provisioning);
    }

    public function test_transfer_approver_resolution_ignores_inactive_permissions(): void
    {
        foreach ([
            'app/Http/Controllers/UbicacionInventarioController.php',
            'app/Services/TransferNotificationService.php',
            'app/Services/TransferWorkflowService.php',
        ] as $file) {
            $contents = $this->read($file);
            self::assertStringContainsString("->where('p.activo', 1)", $contents, $file);
            self::assertStringContainsString("ubicaciones.aprobar_traslados", $contents, $file);
        }
    }

    public function test_routes_and_middleware_protect_role_and_permission_status_operations(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            'seguridad.roles.activate',
            'seguridad.permisos.destroy',
            'seguridad.permisos.activate',
        ] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
            self::assertStringContainsString("'{$routeName}'", $middleware);
        }

        self::assertStringContainsString("'seguridad.administrar'", $middleware);
    }

    public function test_interface_preserves_responsiveness_and_exposes_protected_lifecycle_controls(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');

        foreach ([
            'Rol base protegido.',
            'Clave inmutable.',
            'Administrador SWAFI recibe automáticamente todos los permisos activos.',
            "route('seguridad.roles.activate'",
            "route('seguridad.permisos.destroy'",
            "route('seguridad.permisos.activate'",
            'sec-permission-group',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringContainsString('{{ $permission->clave }}', $view);
        self::assertStringNotContainsString('{!! $permission->clave !!}', $view);
    }

    public function test_audit_actions_fit_the_existing_database_column(): void
    {
        foreach ([
            'SEGURIDAD_ROL_ALTA',
            'SEGURIDAD_ROL_ACTUALIZACION',
            'SEGURIDAD_ROL_ACTIVACION',
            'SEGURIDAD_ROL_DESACTIVACION',
            'SEGURIDAD_PERMISO_ALTA',
            'SEGURIDAD_PERMISO_ACTUALIZACION',
            'SEGURIDAD_PERMISO_ACTIVACION',
            'SEGURIDAD_PERMISO_DESACTIVACION',
            'SEGURIDAD_ROLES_PERMISOS_CONTROL',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action.' supera VARCHAR(40).');
        }
    }

    public function test_existing_user_session_captcha_and_navigation_controls_remain_present(): void
    {
        $userService = $this->read('app/Services/UserAccessManagementService.php');
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');

        self::assertStringContainsString('La operación dejaría a SWAFI sin un Administrador activo.', $userService);
        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('<header class="swafi-page-header">', $layout);
        self::assertStringContainsString('nav-item-logout', $layout);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
