<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DocumentDeactivationSecurityConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_adds_document_deactivation_traceability_and_admin_permission(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_20_000600_restrict_document_deactivation_to_administrators.php'
        );

        foreach ([
            "string('motivo_baja', 500)",
            "timestamp('dado_baja_at')",
            "unsignedBigInteger('dado_baja_por')",
            'doc_exp_baja_usuario_fk',
            'cascadeOnUpdate()',
            'doc_exp_vigente_baja_idx',
            "private const PERMISSION = 'documentos.eliminar'",
            "private const ADMIN_ROLE = 'Administrador SWAFI'",
            "->where('role_id', '<>', \$administratorRoleId)",
            "'registro_clave' => 'HU-015'",
            'public function down(): void',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertLessThanOrEqual(40, strlen('HABILITA_BAJA_DOCUMENTO_ADMIN'));
    }

    public function test_request_requires_an_authorized_administrator_and_a_clear_reason(): void
    {
        $request = $this->read('app/Http/Requests/DeactivateExpedienteDocumentRequest.php');

        foreach ([
            "\$context['is_admin'] === true",
            "in_array('documentos.eliminar', \$context['permissions'], true)",
            "'motivo_baja' => ['required', 'string', 'min:10', 'max:500']",
            'Debes indicar el motivo de la baja lógica del documento.',
            "trim((string) \$this->input('motivo_baja', ''))",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_route_uses_a_dedicated_permission_instead_of_document_upload_permission(): void
    {
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString(
            "'documentos.store' => 'documentos.cargar'",
            $middleware
        );
        self::assertStringContainsString(
            "'documentos.eliminar' => 'documentos.eliminar'",
            $middleware
        );
        self::assertStringNotContainsString(
            "'documentos.eliminar' => 'documentos.cargar'",
            $middleware
        );
    }

    public function test_controller_uses_locking_logical_deactivation_and_record_level_traceability(): void
    {
        $controller = $this->read('app/Http/Controllers/DocumentoExpedienteController.php');
        $destroy = $this->methodBody($controller, 'destroy');

        self::assertStringContainsString(
            'DeactivateExpedienteDocumentRequest $request',
            $controller
        );

        foreach ([
            'DB::transaction(',
            'lockForUpdate: true',
            "'vigente' => false",
            "'motivo_baja' => \$motivoBaja",
            "'dado_baja_at' => \$deactivatedAt",
            "'dado_baja_por' => \$userId",
            "accion: 'DOCUMENTO_BAJA_LOGICA'",
            'El archivo físico, las versiones y la trazabilidad se conservan.',
        ] as $expected) {
            self::assertStringContainsString($expected, $destroy);
        }

        self::assertStringNotContainsString('storage->delete(', $destroy);
        self::assertStringNotContainsString('->delete()', $destroy);
        self::assertStringNotContainsString('->forceDelete(', $destroy);
    }

    public function test_interface_separates_upload_from_admin_only_deactivation_and_keeps_confirmation_accessible(): void
    {
        $view = $this->read('resources/views/swafi/expediente.blade.php');

        foreach ([
            "\$canManageDocuments = \$isAdminSwafi",
            "\$isOfficialAdminSwafi = in_array('Administrador SWAFI', \$swafiRoles, true)",
            "\$canDeactivateDocuments = \$isOfficialAdminSwafi",
            "in_array('documentos.eliminar', \$swafiPermissions, true)",
            '@if($canDeactivateDocuments)',
            'name="motivo_baja"',
            'minlength="10"',
            'maxlength="500"',
            'Confirmar baja lógica',
            'document-deactivation-form',
            'data-confirm="¿Confirmas la baja lógica de este documento?',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringContainsString('{{ old(\'motivo_baja\') }}', $view);
        self::assertStringNotContainsString('{!! old(\'motivo_baja\') !!}', $view);
    }

    public function test_model_and_seeder_preserve_the_new_security_contract(): void
    {
        $model = $this->read('app/Models/DocumentoExpediente.php');
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        foreach ([
            "'motivo_baja'",
            "'dado_baja_at'",
            "'dado_baja_por'",
            "'dado_baja_at' => 'datetime'",
            "belongsTo(User::class, 'dado_baja_por')",
        ] as $expected) {
            self::assertStringContainsString($expected, $model);
        }

        self::assertStringContainsString(
            "['clave' => 'documentos.eliminar'",
            $seeder
        );

        $captureStart = strpos($seeder, '$capturaPermisos =');
        $consultStart = strpos($seeder, '$consultaPermisos =');

        self::assertNotFalse($captureStart);
        self::assertNotFalse($consultStart);

        $captureBlock = substr(
            $seeder,
            (int) $captureStart,
            (int) $consultStart - (int) $captureStart
        );

        self::assertStringContainsString("'documentos.cargar'", $captureBlock);
        self::assertStringNotContainsString("'documentos.eliminar'", $captureBlock);

        $roleService = $this->read('app/Services/RolePermissionManagementService.php');
        $securityView = $this->read('resources/views/swafi/seguridad.blade.php');

        foreach ([
            "private const ADMIN_ONLY_PERMISSION_KEYS = [",
            "'documentos.eliminar'",
            'assertNoAdministratorOnlyPermissions',
            'es exclusivo del Administrador SWAFI y no puede asignarse a otro rol.',
        ] as $expected) {
            self::assertStringContainsString($expected, $roleService);
        }

        self::assertStringContainsString(
            "\$permissionSoloAdministrador = \$permission->clave === 'documentos.eliminar'",
            $securityView
        );
        self::assertStringContainsString('Exclusivo del Administrador SWAFI.', $securityView);
    }

    private function methodBody(string $contents, string $method): string
    {
        $position = strpos($contents, "function {$method}(");
        self::assertNotFalse($position, $method);
        $opening = strpos($contents, '{', (int) $position);
        self::assertNotFalse($opening, $method);
        $depth = 1;
        $cursor = (int) $opening + 1;

        while ($cursor < strlen($contents) && $depth > 0) {
            if ($contents[$cursor] === '{') {
                $depth++;
            } elseif ($contents[$cursor] === '}') {
                $depth--;
            }
            $cursor++;
        }

        return substr($contents, (int) $opening + 1, $cursor - (int) $opening - 2);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
