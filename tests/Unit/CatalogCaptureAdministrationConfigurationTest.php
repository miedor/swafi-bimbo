<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogCaptureAdministrationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_grants_catalog_administration_to_capture_and_is_audited(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_21_000620_grant_catalog_administration_to_capture_role.php'
        );

        foreach ([
            "private const ROLE_NAME = 'Usuario Captura';",
            "private const PERMISSION = 'catalogos.administrar';",
            "private const AUDIT_ACTION = 'HABILITA_CATALOGOS_CAPTURA';",
            "private const AUDIT_KEY = 'ROL-CAPTURA-CATALOGOS';",
            "DB::table('permission_role')->insertOrIgnore",
            'permiso_asignado_previamente',
            'public function down(): void',
            "'seguridad_administrar' => false",
            "'documentos_eliminar' => false",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_fresh_installation_assigns_the_permission_to_capture_without_security_privileges(): void
    {
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');
        $captureBlock = $this->between(
            $seeder,
            '$capturaPermisos = array_merge([',
            '$consultaPermisos = array_merge(['
        );

        self::assertStringContainsString("'catalogos.administrar'", $captureBlock);
        self::assertStringNotContainsString("'seguridad.administrar'", $captureBlock);
        self::assertStringNotContainsString("'documentos.eliminar'", $captureBlock);
        self::assertStringContainsString('administración de catálogos base', $seeder);
    }

    public function test_backend_keeps_the_capture_catalog_permission_as_a_required_system_role_permission(): void
    {
        $service = $this->read('app/Services/RolePermissionManagementService.php');

        foreach ([
            "private const CAPTURE_ROLE = 'Usuario Captura';",
            'private const REQUIRED_PERMISSION_KEYS_BY_SYSTEM_ROLE',
            "'catalogos.administrar'",
            'appendRequiredSystemRolePermissions',
            "->where('activo', 1)",
            'No fue posible conservar la matriz base del rol Usuario Captura',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringContainsString("private const ADMIN_ONLY_PERMISSION_KEYS = [\n        'documentos.eliminar'", $service);
    }

    public function test_all_catalog_mutations_remain_protected_by_catalog_administration_permission(): void
    {
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            "'catalogos.store'",
            "'catalogos.importar'",
            "'catalogos.importaciones.aplicar'",
            "'catalogos.importaciones.cancelar'",
            "'catalogos.plantilla'",
            "'catalogos.destroy'",
            "'catalogos.activate' => 'catalogos.administrar'",
        ] as $expected) {
            self::assertStringContainsString($expected, $middleware);
        }

        self::assertStringContainsString(
            '$this->authorization->refreshSession($request, $userId);',
            $middleware
        );
    }

    public function test_form_requests_authorize_by_permission_in_the_server(): void
    {
        foreach ([
            'app/Http/Requests/StoreCatalogRequest.php',
            'app/Http/Requests/ImportCatalogRequest.php',
            'app/Http/Requests/ApplyCatalogImportRequest.php',
            'app/Http/Requests/UpdateCatalogStatusRequest.php',
        ] as $path) {
            $request = $this->read($path);
            self::assertStringContainsString(
                "\$permissions->contains('catalogos.administrar')",
                $request,
                $path
            );
        }
    }

    public function test_interfaces_explain_capture_administration_and_preserve_responsive_security_controls(): void
    {
        $catalogs = $this->read('resources/views/swafi/catalogos.blade.php');
        $security = $this->read('resources/views/swafi/seguridad.blade.php');
        $dashboard = $this->read('resources/views/swafi/dashboard.blade.php');

        self::assertStringContainsString('Administrador y Captura', $catalogs);
        self::assertStringContainsString('Usuario Captura</strong>', $catalogs);
        self::assertStringContainsString('Administración y carga masiva', $catalogs);

        self::assertStringContainsString("\$rolEsCaptura", $security);
        self::assertStringContainsString("\$permissionRequeridoCaptura", $security);
        self::assertStringContainsString('type="hidden" name="permission_ids[]"', $security);
        self::assertStringContainsString('Requerido para que Usuario Captura administre los catálogos base.', $security);
        self::assertStringContainsString('@media (max-width: 760px)', $security);

        self::assertStringContainsString(
            "\$can('catalogos.administrar') ? 'Administra proveedores, plantas y datos maestros.'",
            $dashboard
        );
    }

    public function test_previous_oracle_and_shared_invoice_decisions_remain_present(): void
    {
        $oracleTest = $this->read('tests/Unit/OracleDepreciationSourceOfTruthConfigurationTest.php');
        $sharedInvoiceTest = $this->read('tests/Unit/SharedInvoiceMultiAssetConfigurationTest.php');

        self::assertStringContainsString('Oracle ERP', $oracleTest);
        self::assertStringContainsString('uuid_cfdi', $sharedInvoiceTest);
    }

    public function test_new_audit_action_fits_the_database_column(): void
    {
        self::assertLessThanOrEqual(40, strlen('HABILITA_CATALOGOS_CAPTURA'));
    }

    private function between(string $contents, string $start, string $end): string
    {
        $startPosition = strpos($contents, $start);
        $endPosition = strpos($contents, $end);

        self::assertNotFalse($startPosition, "No se encontró el inicio: {$start}");
        self::assertNotFalse($endPosition, "No se encontró el final: {$end}");
        self::assertGreaterThan($startPosition, $endPosition);

        return substr($contents, $startPosition, $endPosition - $startPosition);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . ltrim($relativePath, '/'));

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
