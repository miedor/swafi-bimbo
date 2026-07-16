<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InventoryTransferAndClosureConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_migration_creates_transfer_and_inventory_period_traceability(): void
    {
        $migration = $this->read('database/migrations/2026_07_16_000440_create_transfer_approval_and_inventory_lock_tables.php');

        self::assertStringContainsString("Schema::create('periodos_inventario'", $migration);
        self::assertStringContainsString("Schema::create('solicitudes_traslado'", $migration);
        self::assertStringContainsString("uuid('uuid')->unique()", $migration);
        self::assertStringContainsString("'ubicaciones.aprobar_traslados'", $migration);
        self::assertStringContainsString("'ubicaciones.cerrar_inventario'", $migration);
        self::assertStringContainsString("'Usuario Captura' => [", $migration);
        self::assertStringContainsString("'Usuario Consulta / Auditoría' => ['ubicaciones.ver']", $migration);
        self::assertStringContainsString('HABILITA_FLUJO_TRASLADOS_CIERRES', $migration);
        self::assertStringContainsString("DB::table('bitacora_auditoria')->updateOrInsert", $migration);
        self::assertStringContainsString("string('motivo', 500)->nullable()->change()", $migration);
    }


    public function test_audit_action_literals_respect_the_database_column_limit(): void
    {
        $schemaMigration = $this->read('database/migrations/2026_04_19_000270_create_bitacora_auditoria_table.php');

        self::assertSame(
            1,
            preg_match("/string\('accion',\s*(\d+)\)/", $schemaMigration, $matches),
            'No fue posible determinar la longitud de bitacora_auditoria.accion.'
        );

        $maximumLength = (int) $matches[1];
        self::assertSame(40, $maximumLength);

        $patterns = [
            "/['\"]accion['\"]\s*=>\s*['\"]([A-Z][A-Z0-9_]*)['\"]/",
            "/\baction\s*:\s*['\"]([A-Z][A-Z0-9_]*)['\"]/",
            "/\baccion\s*:\s*['\"]([A-Z][A-Z0-9_]*)['\"]/",
            "/\bauditAction\s*:\s*['\"]([A-Z][A-Z0-9_]*)['\"]/",
        ];

        $directories = ['app', 'database', 'routes'];
        $violations = [];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->root.'/'.$directory,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (!is_string($contents)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $contents, $foundActions);

                    foreach ($foundActions[1] ?? [] as $action) {
                        if (strlen($action) > $maximumLength) {
                            $violations[] = sprintf(
                                '%s (%d caracteres) en %s',
                                $action,
                                strlen($action),
                                str_replace($this->root.'/', '', $file->getPathname())
                            );
                        }
                    }
                }
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($violations)),
            'Se detectaron acciones de bitácora que exceden el límite de la columna accion.'
        );
    }

    public function test_cross_plant_transfer_is_not_applied_before_approval(): void
    {
        $service = $this->read('app/Services/TransferWorkflowService.php');

        self::assertStringContainsString('$isCrossPlant', $service);
        self::assertStringContainsString("'estatus' => 'pendiente'", $service);
        self::assertStringContainsString('SOLICITUD_TRASLADO_CREADA', $service);
        self::assertStringContainsString('La ubicación actual no fue modificada', $service);
        self::assertStringContainsString('SOLICITUD_TRASLADO_APROBADA', $service);
        self::assertStringContainsString('SOLICITUD_TRASLADO_RECHAZADA', $service);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString("->first(['id'])", $service);
        self::assertStringContainsString('assertResponsibleActive', $service);
    }

    public function test_approval_revalidates_origin_and_applies_asset_and_movement_atomically(): void
    {
        $service = $this->read('app/Services/TransferWorkflowService.php');

        self::assertStringContainsString('La ubicación actual del activo cambió después de crear la solicitud', $service);
        self::assertStringContainsString("'planta_id' => \$destinationPlantId", $service);
        self::assertStringContainsString("'ubicacion_id' => \$destinationLocationId", $service);
        self::assertStringContainsString('MovimientoUbicacion::create', $service);
        self::assertStringContainsString('DB::transaction', $service);
    }

    public function test_inventory_period_blocks_movements_and_inventory_by_date(): void
    {
        $service = $this->read('app/Services/InventoryPeriodService.php');
        $controller = $this->read('app/Http/Controllers/UbicacionInventarioController.php');

        self::assertStringContainsString("->where('estatus', 'bloqueado')", $service);
        self::assertStringContainsString("whereDate('fecha_inicio', '<='", $service);
        self::assertStringContainsString("whereDate('fecha_fin', '>='", $service);
        self::assertStringContainsString('assertMovementAllowed', $service);
        self::assertStringContainsString('assertInventoryAllowed', $service);
        self::assertStringContainsString('$this->inventoryPeriods->assertInventoryAllowed', $controller);
        self::assertStringContainsString('Registra una solicitud de traslado', $service);
    }

    public function test_routes_are_separated_by_least_privilege_permissions(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $layout = $this->read('resources/views/layouts/app.blade.php');

        foreach ([
            'ubicacion.traslados.aprobar',
            'ubicacion.traslados.rechazar',
            'ubicacion.periodos.store',
            'ubicacion.periodos.bloquear',
            'ubicacion.periodos.desbloquear',
        ] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
            self::assertStringContainsString("'{$routeName}'", $middleware);
        }

        self::assertStringContainsString("'ubicacion' => 'ubicaciones.ver'", $middleware);
        self::assertStringContainsString("'ubicaciones.aprobar_traslados'", $middleware);
        self::assertStringContainsString("'ubicaciones.cerrar_inventario'", $middleware);
        self::assertStringContainsString("'ubicaciones.ver' => [", $middleware);
        self::assertStringContainsString("'ubicaciones.administrar'", $middleware);
        self::assertStringContainsString("'ubicaciones.aprobar_traslados'", $middleware);
        self::assertStringContainsString("'ubicaciones.cerrar_inventario'", $middleware);
        self::assertStringContainsString("\$swafiCan('ubicaciones.ver')", $layout);
    }

    public function test_rejection_and_period_state_changes_require_a_traceable_reason(): void
    {
        $resolveRequest = $this->read('app/Http/Requests/ResolveTransferRequest.php');
        $periodRequest = $this->read('app/Http/Requests/UpdateInventoryPeriodStatusRequest.php');
        $movementRequest = $this->read('app/Http/Requests/StoreMovimientoUbicacionRequest.php');

        self::assertStringContainsString("routeIs('ubicacion.traslados.rechazar')", $resolveRequest);
        self::assertStringContainsString("'min:10'", $resolveRequest);
        self::assertStringContainsString("'motivo_estado'", $periodRequest);
        self::assertStringContainsString("'min:10'", $periodRequest);
        self::assertStringContainsString("'motivo' => [", $movementRequest);
        self::assertStringContainsString("'required'", $movementRequest);
    }

    public function test_interface_keeps_existing_inventory_features_and_adds_control_panels(): void
    {
        $view = $this->read('resources/views/swafi/ubicacion.blade.php');
        $transfers = $this->read('resources/views/swafi/partials/transfer-approvals.blade.php');
        $periods = $this->read('resources/views/swafi/partials/inventory-periods.blade.php');
        $styles = $this->read('public/assets/swafi/css/swafi-inventory-workflow.css');
        $controller = $this->read('app/Http/Controllers/UbicacionInventarioController.php');

        foreach ([
            'Toma de inventario',
            'Evidencias de inventario',
            'Notificar a Consulta / Auditoría',
            'Exportar CSV',
            'Etiqueta QR',
            'data-swafi-query-panel',
            'data-swafi-query-results',
        ] as $existingFeature) {
            self::assertStringContainsString($existingFeature, $view);
        }

        self::assertStringContainsString("@include('swafi.partials.transfer-approvals')", $view);
        self::assertStringContainsString("->paginate(5, ['*'], 'traslados_page')", $controller);
        self::assertStringContainsString("->paginate(5, ['*'], 'periodos_page')", $controller);
        self::assertStringContainsString("@include('swafi.partials.inventory-periods')", $view);
        self::assertStringContainsString('Aprobar y aplicar', $transfers);
        self::assertStringContainsString('Motivo de rechazo', $transfers);
        self::assertStringContainsString('workflow-panel', $transfers);
        self::assertStringContainsString("request('panel') === 'traslados'", $transfers);
        self::assertStringContainsString('hasPages()', $transfers);
        self::assertStringContainsString('Bloquear periodo', $periods);
        self::assertStringContainsString('Desbloquear periodo', $periods);
        self::assertStringContainsString("request('panel') === 'periodos'", $periods);
        self::assertStringContainsString('hasPages()', $periods);
        self::assertStringContainsString('.workflow-panel-summary', $styles);
        self::assertStringContainsString('.workflow-pagination', $styles);
        self::assertStringContainsString('@media (max-width: 760px)', $styles);
    }

    public function test_security_session_and_query_ux_regressions_remain_present(): void
    {
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $queryUx = $this->read('public/assets/swafi/js/swafi-query-results.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');

        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("const FOCUS_PARAMETER = 'swafi_focus';", $queryUx);
        self::assertStringContainsString('position: sticky !important', $layout);
        self::assertStringContainsString('Cerrar sesión', $layout);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
