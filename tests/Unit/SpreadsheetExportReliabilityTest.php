<?php

namespace Tests\Unit;

use App\Services\RegistroMasivoService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\UploadedFile;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetExportReliabilityTest extends TestCase
{
    public function test_xlsx_export_is_generated_in_memory_as_a_valid_archive(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('La extensión ZIP es necesaria para validar la generación XLSX.');
        }

        $exporter = app(SimpleXlsxExporter::class);
        $contents = $exporter->exportBytes(
            'Incidencias/Importación?*[]:',
            ['Fila', 'Estatus', 'Errores'],
            [[2, 'Rechazada', 'El RFC de proveedor no existe o está inactivo.']]
        );

        $this->assertNotSame('', $contents);
        $this->assertStringStartsWith("PK", $contents);

        $path = tempnam(sys_get_temp_dir(), 'swafi_xlsx_test_');
        $this->assertIsString($path);
        file_put_contents($path, $contents);

        $zip = new ZipArchive();

        try {
            $this->assertTrue($zip->open($path, ZipArchive::RDONLY));

            foreach ([
                '[Content_Types].xml',
                'xl/workbook.xml',
                'xl/styles.xml',
                'xl/worksheets/sheet1.xml',
            ] as $entry) {
                $this->assertNotFalse($zip->locateName($entry), $entry);
            }

            $workbook = $zip->getFromName('xl/workbook.xml');
            $this->assertIsString($workbook);
            $this->assertStringContainsString('name="Incidencias Importación"', $workbook);

            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($sheet);
            $this->assertStringContainsString('Rechazada', $sheet);
            $this->assertStringContainsString('RFC de proveedor', $sheet);
        } finally {
            $zip->close();

            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_bulk_csv_reader_preserves_quoted_commas_and_multiline_fields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'swafi_csv_test_');
        $this->assertIsString($path);

        $headers = [
            'Numero activo', 'Descripcion', 'Folio factura', 'UUID CFDI',
            'Fecha factura', 'Monto factura', 'Moneda', 'Proveedor RFC',
            'Tipo activo clave', 'Centro costo clave', 'Planta clave',
            'Ubicacion codigo', 'Responsable correo', 'Serie', 'Marca',
            'Modelo', 'Fecha adquisicion', 'Estatus operativo',
            'Documento PDF', 'Documento XML', 'Observaciones',
        ];

        $handle = fopen($path, 'wb');
        $this->assertIsResource($handle);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ',', '"', '');
        fputcsv($handle, [
            'BIM-541600',
            'BATIDORA LIKWIFIER P GLASES',
            'D884',
            '5B6FE732-C901-4E30-8E75-2E861C7D7576',
            '16/11/2023',
            '24,736.77',
            'USD',
            'ASE0407304B5',
            'EQP',
            'CC-PLA-200',
            'PLT-SM',
            'UBI-SM-PRO-L3-PB',
            'jorge.mendez@bimbo.local',
            'D-597296-1',
            'AMERICAN SEAL',
            'N/A',
            '16/11/2023',
            'en_operacion',
            '5B6FE732.pdf',
            '5B6FE732.xml',
            "Primera línea\nSegunda línea",
        ], ',', '"', '');
        fclose($handle);

        try {
            $file = new UploadedFile($path, 'layout.csv', 'text/csv', null, true);
            $service = app(RegistroMasivoService::class);
            $method = new ReflectionMethod($service, 'readCsvRecords');
            $result = $method->invoke($service, $file);

            $this->assertCount(21, $result['headers']);
            $this->assertCount(1, $result['records']);
            $this->assertSame(21, $result['records'][0]['columnas_esperadas']);
            $this->assertSame(21, $result['records'][0]['columnas_recibidas']);
            $this->assertSame('24,736.77', $result['records'][0]['columns'][5]);
            $this->assertSame("Primera línea\nSegunda línea", $result['records'][0]['columns'][20]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_incident_export_has_a_controlled_csv_fallback(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/RegistroMasivoController.php');
        $routes = file_get_contents($root . '/routes/web.php');
        $view = file_get_contents($root . '/resources/views/swafi/registro-masivo.blade.php');
        $middleware = file_get_contents($root . '/app/Http/Middleware/SwafiAuth.php');

        $this->assertIsString($controller);
        $this->assertIsString($routes);
        $this->assertIsString($view);
        $this->assertIsString($middleware);

        $this->assertStringContainsString('exportarIncidenciasCsv', $controller);
        $this->assertStringContainsString('report($exception);', $controller);
        $this->assertStringContainsString("'registro-masivo.incidencias-csv'", $routes);
        $this->assertStringContainsString("'registro-masivo.incidencias-csv'", $middleware);
        $this->assertStringContainsString('Descargar respaldo CSV', $view);
    }

    public function test_all_xlsx_controllers_use_memory_export_instead_of_binary_temp_download(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/Http/Controllers/RegistroMasivoController.php',
            'app/Http/Controllers/BusquedaController.php',
            'app/Http/Controllers/ReportesController.php',
        ] as $relativePath) {
            $contents = file_get_contents($root . '/' . $relativePath);

            $this->assertIsString($contents, $relativePath);
            $this->assertStringContainsString('exportBytes(', $contents, $relativePath);
        }
    }

    public function test_xlsx_exporter_validates_archive_and_uses_the_operating_system_temp_directory(): void
    {
        $contents = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/SimpleXlsxExporter.php'
        );

        $this->assertIsString($contents);
        $this->assertStringContainsString('sys_get_temp_dir()', $contents);
        $this->assertStringContainsString('validateArchive($path)', $contents);
        $this->assertStringContainsString('REQUIRED_ARCHIVE_ENTRIES', $contents);
        $this->assertStringContainsString('ENT_SUBSTITUTE', $contents);
        $this->assertStringContainsString('file_get_contents($path)', $contents);
    }
}
