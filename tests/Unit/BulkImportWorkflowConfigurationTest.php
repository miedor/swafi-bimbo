<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BulkImportWorkflowConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function test_import_tables_preserve_batch_and_row_traceability(): void
    {
        $migration = $this->read('database/migrations/2026_07_16_000430_create_importaciones_masivas_tables.php');

        self::assertStringContainsString("Schema::create('importaciones_masivas'", $migration);
        self::assertStringContainsString("Schema::create('importacion_masiva_filas'", $migration);
        self::assertStringContainsString("uuid('uuid')->unique()", $migration);
        self::assertStringContainsString("json('datos')", $migration);
        self::assertStringContainsString("json('errores')->nullable()", $migration);
        self::assertStringContainsString("json('advertencias')->nullable()", $migration);
        self::assertStringContainsString("unique(['importacion_id', 'numero_fila'])", $migration);
    }

    public function test_preview_validates_structure_business_rules_and_zip_security(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([
            'validateHeaders',
            'validateRow',
            'MAX_ROWS',
            'MAX_ZIP_FILES',
            'MAX_ZIP_UNCOMPRESSED_BYTES',
            "str_contains(\$entryName, '../')",
            'validateExtractedDocumentContent',
            "str_contains(\$prefix, '%PDF-')",
            "str_contains(\$upperContents, '<!DOCTYPE')",
            'LIBXML_NONET',
            'UUID CFDI no tiene el formato',
            'La ubicación indicada no pertenece a la planta capturada',
            'existe con baja lógica',
        ] as $expected) {
            self::assertStringContainsString($expected, $service, $expected);
        }
    }

    public function test_rows_are_classified_and_only_accepted_rows_are_applied(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        self::assertStringContainsString("\$status = 'rechazada'", $service);
        self::assertStringContainsString("\$status = 'observada'", $service);
        self::assertStringContainsString("\$status = 'aceptada'", $service);
        self::assertStringContainsString("->where('estatus', 'aceptada')", $service);
        self::assertStringContainsString("->where('aplicada', false)", $service);
        self::assertStringContainsString('verifyHash(', $service);
        self::assertStringContainsString('verificación de integridad SHA-256', $service);
        self::assertStringContainsString('DB::beginTransaction();', $service);
        self::assertStringContainsString('DB::rollBack();', $service);
    }

    public function test_routes_require_existing_bulk_import_permission(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            'registro-masivo.aplicar',
            'registro-masivo.cancelar',
            'registro-masivo.incidencias',
        ] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
            self::assertStringContainsString("'{$routeName}'", $middleware);
        }

        self::assertStringContainsString("'registro-masivo.plantilla' => 'expedientes.crear'", $middleware);
    }

    public function test_interface_requires_preview_and_explicit_confirmation(): void
    {
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        self::assertStringContainsString('Previsualizar y validar', $view);
        self::assertStringContainsString('Previsualización del lote', $view);
        self::assertStringContainsString('name="confirmar_aplicacion"', $view);
        self::assertStringContainsString('aplicar solo las filas aceptadas', $view);
        self::assertStringContainsString('Descargar incidencias Excel', $view);
        self::assertStringContainsString('Historial reciente de importaciones', $view);
    }

    public function test_batch_events_are_written_to_the_audit_log(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([
            'IMPORTACION_PREVISUALIZADA',
            'IMPORTACION_LOTE_APLICADA',
            'IMPORTACION_LOTE_CANCELADA',
            'IMPORTACION_EXPEDIENTE_ALTA',
            'IMPORTACION_EXPEDIENTE_ACTUALIZACION',
        ] as $action) {
            self::assertStringContainsString($action, $service);
        }

        self::assertStringContainsString("'tabla_afectada' => \$tablaAfectada", $service);
        self::assertStringContainsString("'fecha_evento' => now()", $service);
    }

    public function test_existing_security_logical_deletion_and_visual_regressions_remain_present(): void
    {
        $sessionScript = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $massController = $this->read('app/Http/Controllers/RegistroMasivoController.php');
        $expedienteModel = $this->read('app/Models/Expediente.php');

        self::assertStringContainsString("terminateSession('navegacion_atras')", $sessionScript);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $sessionScript);
        self::assertStringContainsString('position: sticky !important', $layout);
        self::assertStringContainsString('Cerrar sesión', $layout);
        self::assertStringContainsString("whereNull('e.deleted_at')", $massController);
        self::assertStringContainsString('use SoftDeletes;', $expedienteModel);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->projectRoot . '/' . $relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
