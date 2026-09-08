<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BulkImportAsyncProcessingConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function test_bulk_apply_is_dispatched_to_a_dedicated_background_queue(): void
    {
        $job = $this->read('app/Jobs/AplicarRegistroMasivoJob.php');
        $controller = $this->read('app/Http/Controllers/RegistroMasivoController.php');
        $queue = $this->read('config/queue.php');

        self::assertStringContainsString('implements ShouldQueue', $job);
        self::assertStringContainsString("onConnection('bulk_imports')", $job);
        self::assertStringContainsString("onQueue('swafi-imports')", $job);
        self::assertStringContainsString('RegistroMasivoService $service', $job);
        self::assertStringContainsString('$service->aplicar($batch, $this->userId)', $job);

        self::assertStringContainsString('AplicarRegistroMasivoJob::dispatch(', $controller);
        self::assertStringContainsString("'procesamiento_estado' => 'pendiente'", $controller);
        self::assertStringContainsString('La carga masiva fue enviada a segundo plano', $controller);

        self::assertStringContainsString("'bulk_imports' => [", $queue);
        self::assertStringContainsString("'driver' => 'database'", $queue);
        self::assertStringContainsString("'queue' => 'swafi-imports'", $queue);
        self::assertStringContainsString("env('SWAFI_BULK_QUEUE_RETRY_AFTER', 1500)", $queue);
    }

    public function test_existing_bulk_import_business_logic_remains_transactional_and_unchanged(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([
            'verifyHash(',
            "->where('estatus', 'aceptada')",
            "->where('aplicada', false)",
            'DB::beginTransaction();',
            'DB::commit();',
            'DB::rollBack();',
            'IMPORTACION_LOTE_APLICADA',
            'finalizeRollbackSnapshots',
        ] as $expected) {
            self::assertStringContainsString($expected, $service, $expected);
        }
    }

    public function test_business_state_is_not_changed_to_processing_before_service_execution(): void
    {
        $controller = $this->read('app/Http/Controllers/RegistroMasivoController.php');
        $model = $this->read('app/Models/ImportacionMasiva.php');
        $job = $this->read('app/Jobs/AplicarRegistroMasivoJob.php');

        self::assertStringNotContainsString("'estado' => 'procesando'", $controller);
        self::assertStringNotContainsString("'estado' => 'procesando'", $job);
        self::assertStringContainsString("return \$this->estado === 'previsualizada'", $model);
        self::assertStringContainsString("['pendiente', 'procesando']", $model);
    }

    public function test_processing_metadata_is_persisted_without_replacing_existing_batch_states(): void
    {
        $existingMigration = $this->read(
            'database/migrations/2026_09_04_020000_add_background_processing_to_importaciones_masivas.php'
        );
        $newMigration = $this->read(
            'database/migrations/2026_09_07_230000_add_async_queue_state_to_importaciones_masivas.php'
        );

        foreach ([
            'procesamiento_iniciado_at',
            'procesamiento_finalizado_at',
            'procesamiento_porcentaje',
        ] as $column) {
            self::assertStringContainsString($column, $existingMigration);
        }

        foreach ([
            'procesamiento_estado',
            'procesamiento_solicitado_at',
            'procesamiento_error_referencia',
        ] as $column) {
            self::assertStringContainsString($column, $newMigration);
        }
    }

    public function test_interface_reports_queue_status_and_blocks_duplicate_apply_while_processing(): void
    {
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');
        $controller = $this->read('app/Http/Controllers/RegistroMasivoController.php');

        self::assertStringContainsString('Carga enviada a segundo plano.', $view);
        self::assertStringContainsString('Procesando carga masiva en segundo plano.', $view);
        self::assertStringContainsString('estaEnProcesamiento()', $view);
        self::assertStringContainsString('window.location.reload()', $view);
        self::assertStringContainsString('scheduleRefresh()', $view);
        self::assertStringContainsString("document.addEventListener('visibilitychange'", $view);
        self::assertStringContainsString("nonce=\"{{ request()->attributes->get('csp_nonce') }}\"", $view);
        self::assertStringContainsString("whereNull('procesamiento_estado')", $controller);
        self::assertStringContainsString("orWhereIn('procesamiento_estado', ['error', 'completado'])", $controller);
        self::assertStringContainsString('No es posible cancelar el lote mientras está en cola o procesándose.', $controller);
    }

    public function test_applied_batch_keeps_polling_until_background_job_finishes_and_shows_persistent_summary(): void
    {
        $model = $this->read('app/Models/ImportacionMasiva.php');
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        self::assertStringContainsString(
            "in_array(\$this->estado, ['previsualizada', 'aplicada'], true)",
            $model
        );
        self::assertStringContainsString(
            "in_array(\$this->procesamiento_estado, ['pendiente', 'procesando'], true)",
            $model
        );
        self::assertStringContainsString('Carga masiva aplicada correctamente.', $view);
        self::assertStringContainsString("data_get(\$lote->resumen, 'aplicacion'", $view);
        self::assertStringContainsString('Activos creados', $view);
        self::assertStringContainsString('Activos actualizados', $view);
        self::assertStringContainsString('No aplicados (observados/rechazados)', $view);
    }

    public function test_non_revertible_applied_batch_does_not_show_the_hu029_warning_panel(): void
    {
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        self::assertStringContainsString(
            "\$canRollbackImports && \$lote->estado === 'aplicada' && \$lote->esRevertible()",
            $view
        );
        self::assertStringNotContainsString('motivoNoRevertible()', $view);
        self::assertStringContainsString('HU-029 · Reversión administrativa controlada', $view);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->projectRoot . '/' . $relativePath);
        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
