<?php

namespace Tests\Unit;

use App\Services\RegistroMasivoService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\UploadedFile;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class BulkRegistrationXlsxRoundTripTest extends TestCase
{
    public function test_official_xlsx_template_can_be_read_by_the_bulk_registration_service(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('La extensión ZIP es necesaria para validar XLSX.');
        }

        $headers = $this->headers();
        $row = $this->exampleRow();
        $bytes = app(SimpleXlsxExporter::class)->exportBytes(
            'Registro masivo',
            $headers,
            [$row]
        );
        $path = tempnam(sys_get_temp_dir(), 'swafi_bulk_xlsx_');

        $this->assertIsString($path);
        file_put_contents($path, $bytes);

        try {
            $file = new UploadedFile(
                $path,
                'plantilla_registro_masivo.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );
            $service = app(RegistroMasivoService::class);
            $method = new ReflectionMethod($service, 'readLayoutRecords');
            $result = $method->invoke($service, $file);

            $this->assertSame('numero_activo', $result['headers'][0]);
            $this->assertSame('observaciones', $result['headers'][20]);
            $this->assertCount(21, $result['headers']);
            $this->assertCount(1, $result['records']);
            $this->assertSame(2, $result['records'][0]['numero_fila']);
            $this->assertSame(21, $result['records'][0]['columnas_esperadas']);
            $this->assertSame(21, $result['records'][0]['columnas_recibidas']);
            $this->assertSame($row[0], $result['records'][0]['columns'][0]);
            $this->assertSame($row[20], $result['records'][0]['columns'][20]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_excel_date_serials_are_converted_before_business_validation(): void
    {
        $service = app(RegistroMasivoService::class);
        $method = new ReflectionMethod($service, 'parseDateStrict');

        $this->assertSame('2026-06-25', $method->invoke($service, '46198'));
        $this->assertSame('2026-06-25', $method->invoke($service, '46198.75'));
        $this->assertSame('2026-06-25', $method->invoke($service, '25/06/2026'));
        $this->assertNull($method->invoke($service, 'fecha-no-valida'));
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'Numero activo',
            'Descripcion',
            'Folio factura',
            'UUID CFDI',
            'Fecha factura',
            'Monto factura',
            'Moneda',
            'Proveedor RFC',
            'Tipo activo clave',
            'Centro costo clave',
            'Planta clave',
            'Ubicacion codigo',
            'Responsable correo',
            'Serie',
            'Marca',
            'Modelo',
            'Fecha adquisicion',
            'Estatus operativo',
            'Documento PDF',
            'Documento XML',
            'Observaciones',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function exampleRow(): array
    {
        return [
            'BIM-537028',
            'ARTESA N° 1',
            'FAC-000184',
            'A1B2C3D4-E5F6-7890-ABCD-000000000184',
            '25/06/2026',
            '602700',
            'MXN',
            'ACM010101ABC',
            'EQP',
            'CC-PLA-200',
            'PLT-SM',
            'UBI-SM-PRO-L3-PB',
            'jorge.mendez@bimbo.local',
            'SER-537028',
            'Bimbo Industrial',
            'ART-2026',
            '25/06/2026',
            'en_operacion',
            'factura_184.pdf|evidencia_recepcion_184.pdf',
            'factura_184.xml|complemento_184.xml',
            'Carga masiva XLSX con varios documentos.',
        ];
    }
}
