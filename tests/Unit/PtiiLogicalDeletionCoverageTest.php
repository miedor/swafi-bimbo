<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PtiiLogicalDeletionCoverageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_every_business_delete_route_uses_a_traceable_lifecycle_strategy(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            'expedientes.eliminar',
            'documentos.eliminar',
            'inventario-evidencias.eliminar',
            'busquedas-guardadas.destroy',
            'reportes-guardados.destroy',
            'reportes-programados.destroy',
            'valores.destroy',
            'seguridad.usuarios.destroy',
            'seguridad.roles.destroy',
            'seguridad.permisos.destroy',
            'catalogos.destroy',
        ] as $routeName) {
            self::assertStringContainsString("name('{$routeName}')", $routes, $routeName);
        }
    }

    public function test_record_models_with_delete_semantics_use_soft_deletes(): void
    {
        foreach ([
            'app/Models/Expediente.php',
            'app/Models/ValorActivo.php',
            'app/Models/BusquedaGuardada.php',
            'app/Models/ReporteGuardado.php',
            'app/Models/ReporteProgramado.php',
        ] as $path) {
            $model = $this->read($path);
            self::assertStringContainsString('SoftDeletes', $model, $path);
        }
    }

    public function test_documents_and_inventory_evidence_are_deactivated_without_deleting_the_physical_file(): void
    {
        $documents = $this->methodBody(
            $this->read('app/Http/Controllers/DocumentoExpedienteController.php'),
            'destroy'
        );
        $evidence = $this->methodBody(
            $this->read('app/Http/Controllers/InventarioEvidenciaController.php'),
            'destroy'
        );

        foreach ([$documents, $evidence] as $body) {
            self::assertStringContainsString("'vigente' => false", $body);
            self::assertStringNotContainsString('storage->delete(', $body);
            self::assertStringNotContainsString('->forceDelete(', $body);
        }
    }

    public function test_users_roles_permissions_and_catalogs_use_activation_status_instead_of_physical_deletion(): void
    {
        $users = $this->read('app/Services/UserAccessManagementService.php');
        $roles = $this->read('app/Services/RolePermissionManagementService.php');
        $catalogs = $this->read('app/Services/CatalogManagementService.php');

        self::assertStringContainsString('Activa o desactiva una cuenta sin destruir su historial.', $users);
        self::assertStringContainsString("'estatus' => $nextStatus", $users);
        self::assertStringContainsString("'activo' => $nextStatus === 'activo' ? 1 : 0", $roles);
        self::assertStringContainsString('assertCatalogCanBeDeactivated', $catalogs);
        self::assertStringNotContainsString("DB::table('users')->where('id', $targetUserId)->delete()", $users);
        self::assertStringNotContainsString("DB::table('roles')->where('id', $roleId)->delete()", $roles);
        self::assertStringNotContainsString("DB::table('permissions')->where('id', $permissionId)->delete()", $roles);
    }

    public function test_physical_database_deletes_are_limited_to_pivots_sessions_or_temporary_tokens(): void
    {
        $allowedTables = ['permission_role', 'role_user', 'sessions', 'password_reset_tokens'];
        $app = $this->allApplicationPhp();

        preg_match_all("/DB::table\('([^']+)'\)[\\s\\S]{0,220}?->delete\(\)/", $app, $matches);

        foreach ($matches[1] ?? [] as $table) {
            self::assertContains($table, $allowedTables, "Eliminación física no justificada en {$table}");
        }
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

    private function allApplicationPhp(): string
    {
        $contents = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root.'/app'));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $value = file_get_contents($file->getPathname());
                self::assertIsString($value);
                $contents .= "\n".$value;
            }
        }

        return $contents;
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root.'/'.$relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}
