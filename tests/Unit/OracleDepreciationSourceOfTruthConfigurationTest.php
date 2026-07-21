<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class OracleDepreciationSourceOfTruthConfigurationTest extends TestCase
{
    private string $root;

    /** @var list<string> */
    private array $legacyReferenceFields = [
        'metodo_depreciacion',
        'fecha_inicio_depreciacion',
        'valor_residual',
        'depreciacion_estimada',
        'valor_en_libros_estimado',
        'calculo_depreciacion_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_referential_depreciation_calculator_and_its_obsolete_tests_are_removed(): void
    {
        foreach ([
            'app/Services/DepreciacionReferencialService.php',
            'tests/Unit/DepreciacionReferencialServiceTest.php',
            'tests/Unit/FinancialCatalogsAndDepreciationConfigurationTest.php',
        ] as $removedPath) {
            self::assertFileDoesNotExist(
                $this->root . '/' . $removedPath,
                "El archivo obsoleto {$removedPath} no debe permanecer en SWAFI."
            );
        }
    }

    public function test_values_controller_stores_oracle_values_without_calculating_or_recalculating_them(): void
    {
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');

        foreach ([
            "'depreciacion_acumulada' => \$data['depreciacion_acumulada']",
            "'valor_en_libros' => \$data['valor_en_libros']",
            'Los valores oficiales provenientes de Oracle ERP',
            "'depreciacion_acumulada'",
            "'valor_en_libros'",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }

        foreach ([
            'DepreciacionReferencialService',
            'resolveDepreciationReference',
            'resolveValorEnLibros',
            'valor_fiscal - depreciacion_acumulada',
            'valor_fiscal - $payload[\'depreciacion_acumulada\']',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $controller);
        }

        foreach ($this->legacyReferenceFields as $removedField) {
            self::assertStringNotContainsString($removedField, $controller);
        }
    }

    public function test_request_requires_official_oracle_book_value_without_cross_field_recalculation(): void
    {
        $request = $this->read('app/Http/Requests/StoreValorActivoRequest.php');

        foreach ([
            "'depreciacion_acumulada' => ['required'",
            "'valor_en_libros' => ['required'",
            'La depreciación acumulada oficial de Oracle ERP es obligatoria.',
            'El valor en libros oficial de Oracle ERP es obligatorio.',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }

        foreach ([
            'La depreciación acumulada no puede superar el valor fiscal.',
            'El valor en libros no puede superar el valor fiscal.',
            'valorFiscal -',
            'valor_fiscal -',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $request);
        }

        foreach ($this->legacyReferenceFields as $removedField) {
            self::assertStringNotContainsString($removedField, $request);
        }
    }

    public function test_user_interface_only_captures_official_oracle_values_and_has_no_calculator(): void
    {
        $view = $this->read('resources/views/swafi/valores.blade.php');

        foreach ([
            'Depreciación acumulada (Oracle ERP)',
            'Valor en libros (Oracle ERP)',
            'SWAFI no calcula depreciación ni valor en libros.',
            'oficiales obtenidos de Oracle ERP',
            'name="depreciacion_acumulada"',
            'name="valor_en_libros"',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        foreach ([
            'Calcular depreciación',
            'Previsualizar depreciación',
            'Depreciación estimada',
            'Valor en libros estimado',
            'Línea recta',
            'se validan y concilian contra el XML CFDI',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $view);
        }

        foreach ($this->legacyReferenceFields as $removedField) {
            self::assertStringNotContainsString($removedField, $view);
        }
    }

    public function test_current_model_configuration_and_exports_do_not_use_legacy_reference_fields(): void
    {
        $currentApplication = implode("\n", [
            $this->read('app/Models/ValorActivo.php'),
            $this->read('app/Services/FinancialCatalogService.php'),
            $this->read('app/Services/ValorActivoFichaService.php'),
            $this->read('app/Services/ValorActivoHistoryService.php'),
            $this->read('app/Http/Controllers/ReportesController.php'),
            $this->read('app/Http/Controllers/ValorActivoHistoryController.php'),
            $this->read('resources/views/swafi/expediente.blade.php'),
            $this->read('resources/views/swafi/valores-historial.blade.php'),
            $this->read('resources/views/swafi/reportes.blade.php'),
            $this->read('config/swafi.php'),
        ]);

        foreach ([
            'Depreciación acumulada (Oracle ERP)',
            'Valor en libros (Oracle ERP)',
        ] as $expected) {
            self::assertStringContainsString($expected, $currentApplication);
        }

        foreach ($this->legacyReferenceFields as $removedField) {
            self::assertStringNotContainsString($removedField, $currentApplication);
        }

        self::assertStringNotContainsString("'depreciacion' => [", $currentApplication);
        self::assertStringNotContainsString('depreciationMethods', $currentApplication);
    }

    public function test_shared_invoice_and_technical_xml_rules_remain_unchanged(): void
    {
        $sharedInvoiceTest = $this->read('tests/Unit/SharedInvoiceMultiAssetConfigurationTest.php');
        $cfdiService = $this->read('app/Services/CfdiValidationService.php');

        foreach ([
            'Un mismo folio o UUID puede relacionarse con varios activos',
            'no los compara contra el activo, el proveedor, el folio, el UUID, la fecha, el monto, la moneda ni los valores fiscales o financieros registrados',
            'validación técnica de estructura e integridad',
            'LIBXML_NONET',
        ] as $expected) {
            self::assertStringContainsString($expected, $sharedInvoiceTest . "\n" . $cfdiService);
        }

        foreach ([
            'El valor fiscal no puede superar el total del CFDI asociado.',
            'El tipo de cambio debe coincidir con el CFDI',
            'UUID duplicado',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $cfdiService);
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
