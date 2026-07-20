<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BulkRegistrationXlsxConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_records_the_layout_format_without_renaming_legacy_columns(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_20_000580_add_xlsx_format_to_bulk_imports.php'
        );
        $model = $this->read('app/Models/ImportacionMasiva.php');

        foreach ([
            "Schema::hasTable('importaciones_masivas')",
            "Schema::hasColumn('importaciones_masivas', 'layout_formato')",
            "\$table->string('layout_formato', 8)",
            "->default('csv')",
            "HABILITA_LAYOUT_XLSX_MASIVO",
            "HU-017/HU-018",
            "dropColumn('layout_formato')",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString("'layout_formato'", $model);
        self::assertStringContainsString("'csv_nombre_original'", $model);
        self::assertStringContainsString("'csv_ruta'", $model);
    }

    public function test_upload_request_accepts_only_csv_txt_or_xlsx_with_controlled_size_and_mime(): void
    {
        $request = $this->read('app/Http/Requests/ImportRegistroMasivoRequest.php');

        foreach ([
            "'extensions:csv,txt,xlsx'",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
            "'max:10240'",
            'extensión CSV, TXT o XLSX',
            'layout CSV o XLSX',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }

        self::assertStringNotContainsString("'extensions:csv,txt'", $request);
        self::assertStringNotContainsString("'mimes:csv,txt,xlsx'", $request);
    }

    public function test_bulk_service_reuses_the_hardened_xlsx_reader_and_preserves_preview_rules(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([
            'private readonly SimpleXlsxReader $xlsxReader',
            'private function readLayoutRecords(UploadedFile $layoutFile): array',
            "\$this->xlsxReader->readFirstWorksheet(\$path, self::MAX_ROWS)",
            "private function layoutFormat(UploadedFile \$layoutFile): string",
            "'layout.' . \$layoutFormat",
            "'layout_formato' => \$layoutFormat",
            "'estado' => 'previsualizada'",
            'validateHeaders($normalizedHeaders)',
            'validateRow(',
            'DB::transaction(',
            'IMPORTACION_PREVISUALIZADA',
            "new \\DateTimeImmutable('1899-12-30')",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringNotContainsString('PhpSpreadsheet', $service);
        self::assertStringNotContainsString('base64_decode(', $service);
    }

    public function test_official_excel_template_route_is_authorized_and_csv_compatibility_is_preserved(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $controller = $this->read('app/Http/Controllers/RegistroMasivoController.php');
        $combined = $routes . "\n" . $middleware . "\n" . $controller;

        foreach ([
            "/registro-masivo/plantilla-xlsx",
            "->name('registro-masivo.plantilla-xlsx')",
            "'registro-masivo.plantilla-xlsx'",
            "=> 'expedientes.crear'",
            'public function plantillaXlsx(): RedirectResponse|StreamedResponse',
            "exportBytes(",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'plantilla_registro_masivo_expedientes_swafi.xlsx',
            'bulk_import_xlsx_template',
            'SafeExceptionReporter::class',
            'public function plantillaCsv(): StreamedResponse',
            'plantilla_registro_masivo_expedientes_swafi.csv',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_interface_offers_excel_and_csv_without_adding_an_extra_screen(): void
    {
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        foreach ([
            'layout CSV o Excel',
            'CSV/XLSX + ZIP documental',
            'accept=".csv,.txt,.xlsx"',
            "route('registro-masivo.plantilla-xlsx')",
            'Descargar plantilla Excel',
            "route('registro-masivo.plantilla')",
            'Descargar plantilla CSV',
            "strtoupper(\$lote->layout_formato ?? 'csv')",
            'Previsualizar y validar',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertDoesNotMatchRegularExpression(
            '/\s(?:onclick|onchange|onsubmit)\s*=/i',
            $view
        );
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}
