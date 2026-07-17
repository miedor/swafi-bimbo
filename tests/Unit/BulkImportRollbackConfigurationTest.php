<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BulkImportRollbackConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_adds_reversal_traceability_and_admin_permission(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_17_000460_add_controlled_rollback_to_bulk_imports.php'
        );

        foreach ([
            'reversion_disponible_hasta',
            'revertida_at',
            'revertida_por',
            'motivo_reversion',
            'reversion_resumen',
            'importaciones_masivas_reversion_idx',
            'expedientes.revertir_importacion',
            'Administrador SWAFI',
            'HABILITA_REVERSION_IMPORTACION',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString("->constrained('users')", $migration);
        self::assertStringContainsString('->nullOnDelete()', $migration);
        self::assertStringContainsString("DB::table('permissions')->updateOrInsert", $migration);
        self::assertStringContainsString("DB::table('permission_role')->insertOrIgnore", $migration);
    }

    public function test_form_request_requires_permission_reason_and_explicit_confirmation(): void
    {
        $request = $this->read(
            'app/Http/Requests/RevertImportacionMasivaRequest.php'
        );

        self::assertStringContainsString(
            "canCurrentUser('expedientes.revertir_importacion')",
            $request
        );
        self::assertStringContainsString("'motivo_reversion'", $request);
        self::assertStringContainsString("'min:20'", $request);
        self::assertStringContainsString("'max:500'", $request);
        self::assertStringContainsString("'confirmar_reversion'", $request);
        self::assertStringContainsString("'accepted'", $request);
        self::assertStringContainsString('abort(403', $request);
    }

    public function test_application_captures_before_and_after_snapshots_without_removing_documents(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');
        $config = $this->read('config/swafi.php');

        foreach ([
            'finalizeRollbackSnapshots',
            "'before' => [",
            "'after' => null",
            "'documents' => \$savedDocuments",
            'snapshotActivo',
            'snapshotExpediente',
            'snapshotValorActivo',
            'documentSnapshotFromModel',
            'reversion_disponible_hasta',
            "config('swafi.importaciones.reversion_horas', 24)",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringContainsString('SWAFI_IMPORT_ROLLBACK_HOURS', $config);
        self::assertStringContainsString('168', $config);
        self::assertStringContainsString("'previous' => \$previousSnapshots", $service);
        self::assertStringNotContainsString('deleteDocumentPermanently', $service);
    }

    public function test_rollback_is_atomic_locked_and_rejects_later_dependencies(): void
    {
        $service = $this->read('app/Services/BulkImportRollbackService.php');

        foreach ([
            'DB::transaction(',
            'lockForUpdate()',
            'assertCurrentStateMatches',
            'assertNoLaterDependencies',
            'documentos_expediente',
            'expediente_observaciones',
            'movimientos_ubicacion',
            'inventarios_activo',
            'inventario_evidencias',
            'solicitudes_traslado',
            'IMPORTACION_LOTE_REVERTIDA',
            'IMPORTACION_FILA_REVERTIDA',
            'valores_dados_baja',
            "'estatus_contable' => 'baja'",
            'JSON_THROW_ON_ERROR',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringContainsString("'deleted_at' => now()", $service);
        self::assertStringContainsString("'activo' => false", $service);
        self::assertStringContainsString("'vigente' => false", $service);
        self::assertStringNotContainsString("DB::table('documentos_expediente')->delete", $service);
        self::assertStringNotContainsString("DB::table('expedientes')->delete", $service);
        self::assertStringNotContainsString("DB::table('activos')->delete", $service);
    }

    public function test_reimport_only_restores_records_archived_by_controlled_rollback(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        self::assertStringContainsString('isRollbackArchivedExpediente', $service);
        self::assertStringContainsString("'[IMPORT_ROLLBACK]'", $service);
        self::assertStringContainsString("\$action = 'restaurar'", $service);
        self::assertStringContainsString('IMPORTACION_EXP_RESTAURADA', $service);
        self::assertStringContainsString('$existing->restore()', $service);
        self::assertStringContainsString('requiere restauración autorizada', $service);
        self::assertStringContainsString('más de un expediente para el mismo activo', $service);
    }

    public function test_route_and_middleware_protect_the_reversal_from_direct_url_access(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $controller = $this->read('app/Http/Controllers/RegistroMasivoController.php');

        self::assertStringContainsString("->name('registro-masivo.revertir')", $routes);
        self::assertStringContainsString("->whereUuid('lote')", $routes);
        self::assertStringContainsString(
            "'registro-masivo.revertir' => 'expedientes.revertir_importacion'",
            $middleware
        );
        self::assertStringContainsString('RevertImportacionMasivaRequest', $controller);
        self::assertStringContainsString('BulkImportRollbackService', $controller);
        self::assertStringContainsString('findViewableBatch', $controller);
    }

    public function test_interface_preserves_existing_workflow_and_adds_a_controlled_admin_action(): void
    {
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        foreach ([
            'Previsualizar y validar',
            'Descargar incidencias Excel',
            'Descargar respaldo CSV',
            'Aplicar carga',
            'Cancelar lote',
            'Historial reciente de importaciones',
            'HU-029 · Reversión administrativa controlada',
            'name="motivo_reversion"',
            'name="confirmar_reversion"',
            'Revertir lote aplicado',
            '$canRollbackImports',
            '(int) $lote->user_id === (int) auth()->id()',
            'Valores dados de baja lógica',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
    }

    public function test_existing_session_export_logical_deletion_and_query_ux_regressions_remain_present(): void
    {
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $queryUx = $this->read('public/assets/swafi/js/swafi-query-results.js');
        $exporter = $this->read('app/Services/SimpleXlsxExporter.php');
        $expediente = $this->read('app/Models/Expediente.php');
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("const FOCUS_PARAMETER = 'swafi_focus';", $queryUx);
        self::assertStringContainsString('exportBytes', $exporter);
        self::assertStringContainsString('use SoftDeletes;', $expediente);
        self::assertStringContainsString('data-swafi-query-panel', $view);
        self::assertStringContainsString('data-swafi-query-results', $view);
    }

    public function test_new_audit_actions_fit_the_existing_forty_character_column(): void
    {
        $files = [
            'database/migrations/2026_07_17_000460_add_controlled_rollback_to_bulk_imports.php',
            'app/Services/BulkImportRollbackService.php',
            'app/Services/RegistroMasivoService.php',
        ];

        foreach ($files as $file) {
            $contents = $this->read($file);
            preg_match_all("/'([A-Z][A-Z0-9_]{5,})'/", $contents, $matches);

            foreach ($matches[1] as $action) {
                if (!str_contains($action, 'IMPORT') && !str_contains($action, 'REVERSION')) {
                    continue;
                }

                self::assertLessThanOrEqual(
                    40,
                    strlen($action),
                    "La acción {$action} supera la longitud permitida por bitacora_auditoria.accion."
                );
            }
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
