<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PlantObservationWorkflowConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_repairs_plant_attention_permission_and_queue_index(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_22_000640_repair_plant_observation_workflow.php'
        );

        self::assertStringContainsString("private const ROLE_NAME = 'Usuario Planta / Inventarios';", $migration);
        self::assertStringContainsString("private const PERMISSION_KEY = 'observaciones.atender';", $migration);
        self::assertStringContainsString("insertOrIgnore", $migration);
        self::assertStringContainsString("private const INDEX_NAME = 'idx_obs_assignee_queue';", $migration);
        self::assertStringContainsString("'bandeja_asignaciones' => true", $migration);
        self::assertStringContainsString('public function down(): void', $migration);
    }

    public function test_assignment_requires_the_role_and_active_attention_permission(): void
    {
        $controller = $this->read('app/Http/Controllers/ExpedienteObservacionController.php');

        self::assertStringContainsString("->join('permission_role as pr'", $controller);
        self::assertStringContainsString("->join('permissions as p'", $controller);
        self::assertStringContainsString("->where('p.clave', 'observaciones.atender')", $controller);
        self::assertStringContainsString("->where('p.activo', 1)", $controller);
        self::assertStringContainsString('assertRealMailTransport', $controller);
        self::assertStringContainsString('visible en el Dashboard del usuario responsable', $controller);
    }

    public function test_dashboard_exposes_a_personal_assignment_queue_and_keeps_audit_validation_queue(): void
    {
        $controller = $this->read('app/Http/Controllers/DashboardController.php');
        $service = $this->read('app/Services/ObservationAssignmentQueueService.php');
        $view = $this->read('resources/views/swafi/dashboard.blade.php');

        self::assertStringContainsString('ObservationAssignmentQueueService', $controller);
        self::assertStringContainsString("'observaciones_asignadas_atencion'", $controller);
        self::assertStringContainsString("->where('o.asignado_a', \$userId)", $service);
        self::assertStringContainsString("->whereIn('o.estatus', ['abierta', 'en_atencion', 'rechazada'])", $service);
        self::assertStringContainsString('Mis observaciones', $view);
        self::assertStringContainsString('data-open-attention-queue', $view);
        self::assertStringContainsString('Abrir y atender', $view);
        self::assertStringContainsString('Validaciones pendientes', $view);
    }

    public function test_new_audit_action_fits_the_existing_varchar_40_column(): void
    {
        self::assertLessThanOrEqual(40, strlen('REPARA_FLUJO_OBS_PLANTA'));
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root.'/'.ltrim($path, '/'));

        self::assertIsString($contents, "No fue posible leer {$path}.");

        return $contents;
    }
}
