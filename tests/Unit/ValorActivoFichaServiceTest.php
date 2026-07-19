<?php

namespace Tests\Unit;

use App\Services\ValorActivoFichaService;
use PHPUnit\Framework\TestCase;

class ValorActivoFichaServiceTest extends TestCase
{
    private ValorActivoFichaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ValorActivoFichaService();
    }

    public function test_xlsx_payload_preserves_numeric_values_for_analysis(): void
    {
        $payload = $this->service->xlsxPayload($this->record());

        self::assertSame('Ficha BIM-000001', $payload['title']);
        self::assertSame('Número de activo', $payload['headers'][0]);
        self::assertSame('BIM-000001', $payload['rows'][0][0]);
        self::assertSame(602700.0, $payload['rows'][0][10]);
        self::assertSame(580000.0, $payload['rows'][0][12]);
        self::assertSame(120, $payload['rows'][0][20]);
        self::assertSame('Vigente', $payload['rows'][0][22]);
        self::assertSame('Coincidente', $payload['rows'][0][23]);
    }

    public function test_pdf_payload_groups_identification_invoice_values_and_traceability(): void
    {
        $payload = $this->service->pdfPayload($this->record());
        $sections = array_values(array_unique(array_column($payload['rows'], 0)));

        self::assertSame('Ficha fiscal y financiera · BIM-000001', $payload['title']);
        self::assertSame(['Sección', 'Campo', 'Valor'], $payload['headers']);
        self::assertContains('Identificación', $sections);
        self::assertContains('Factura', $sections);
        self::assertContains('Valores', $sections);
        self::assertContains('Conciliación CFDI', $sections);
        self::assertContains('Trazabilidad', $sections);
        self::assertContains(
            ['Valores', 'Valor fiscal', 'MXN 580,000.00'],
            $payload['rows']
        );
        self::assertContains(
            ['Factura', 'Monto', 'MXN 602,700.00'],
            $payload['rows']
        );
    }

    private function record(): object
    {
        return (object) [
            'valor_id' => 1,
            'numero_activo' => 'BIM-000001',
            'valor_fiscal' => '580000.00',
            'valor_financiero' => '590000.00',
            'moneda' => 'MXN',
            'tipo_cambio' => '1.000000',
            'fecha_tipo_cambio' => null,
            'origen_tipo_cambio' => null,
            'depreciacion_acumulada' => '100000.00',
            'valor_en_libros' => '480000.00',
            'vida_util_meses' => 120,
            'estatus_contable' => 'vigente',
            'motivo_cambio' => 'Registro inicial conciliado.',
            'conciliacion_cfdi' => 'coincidente',
            'conciliacion_detalle' => [],
            'fecha_corte' => '2026-07-19',
            'created_at' => '2026-07-19 10:00:00',
            'updated_at' => '2026-07-19 11:00:00',
            'activo_descripcion' => 'Equipo industrial de prueba',
            'estatus_operativo' => 'en_operacion',
            'estatus_documental' => 'completo',
            'expediente_id' => 1,
            'folio_factura' => 'FAC-2026-001',
            'uuid_cfdi' => '123E4567-E89B-12D3-A456-426614174000',
            'fecha_factura' => '2026-06-25',
            'monto_factura' => '602700.00',
            'moneda_factura' => 'MXN',
            'proveedor_nombre' => 'Proveedor industrial',
            'proveedor_rfc' => 'AAA010101AAA',
            'centro_costo_clave' => 'CC-001',
            'centro_costo_descripcion' => 'Producción',
            'planta_nombre' => 'Santa María',
            'tipo_activo' => 'Equipo industrial',
            'cfdi_estatus' => 'valido',
            'cfdi_total' => '602700.00',
            'cfdi_moneda' => 'MXN',
            'cfdi_uuid' => '123E4567-E89B-12D3-A456-426614174000',
            'registrado_por_nombre' => 'Usuario Captura',
            'registrado_por_email' => 'captura@example.test',
        ];
    }
}
