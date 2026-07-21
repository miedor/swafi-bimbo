<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SharedInvoiceMultiAssetConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_database_allows_the_same_uuid_for_multiple_assets_without_losing_search_performance(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_20_000610_allow_shared_invoice_identifiers.php'
        );

        foreach ([
            "dropUnique(self::UNIQUE_INDEX)",
            "index('uuid_cfdi', self::NON_UNIQUE_INDEX)",
            "HABILITA_FACTURA_COMPARTIDA_MULTIACTIVO",
            "uuid_cfdi_compartido_entre_activos",
            "FACTURA_COMPARTIDA_MULTIACTIVO",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString(
            'No es posible restaurar la restricción única de UUID porque existen facturas compartidas entre varios activos.',
            $migration
        );
    }

    public function test_individual_registration_and_editing_keep_uuid_format_but_remove_global_uniqueness(): void
    {
        $request = $this->read('app/Http/Requests/StoreRegistroIndividualRequest.php');
        $controller = $this->read('app/Http/Controllers/ExpedienteGestionController.php');
        $combined = $request . "\n" . $controller;

        self::assertStringContainsString("'regex:/^[A-F0-9\\-]{32,36}$/'", $request);
        self::assertStringContainsString(
            "Rule::unique('expedientes', 'folio_factura')",
            $request
        );
        self::assertStringContainsString(
            '->where(\'numero_activo\', $numeroActivo)',
            $controller
        );

        foreach ([
            "Rule::unique('expedientes', 'uuid_cfdi')",
            'uuid_cfdi.unique',
            '$uuidConflict',
            'El UUID CFDI ya está registrado en otro expediente.',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $combined);
        }
    }

    public function test_bulk_registration_accepts_repeated_uuid_and_preserves_other_integrity_rules(): void
    {
        $service = $this->read('app/Services/RegistroMasivoService.php');
        $view = $this->read('resources/views/swafi/registro-masivo.blade.php');

        foreach ([
            'El UUID CFDI no tiene el formato 8-4-4-4-12 esperado.',
            '$key = Str::lower($data[\'numero_activo\'] . \'|\' . $data[\'folio_factura\']);',
            'La combinación activo/folio está repetida',
            'Un mismo folio o UUID puede relacionarse con varios activos',
        ] as $expected) {
            self::assertStringContainsString($expected, $service . $view);
        }

        foreach ([
            '$seenUuids',
            '$uuidConflict',
            'El UUID CFDI está repetido en la fila',
            'ya está registrado en otro expediente',
            'UUID duplicado',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $service . $view);
        }
    }

    public function test_cfdi_validation_is_technical_and_does_not_compare_against_asset_records(): void
    {
        $service = $this->read('app/Services/CfdiValidationService.php');

        foreach ([
            "'coincide_uuid' => null",
            "'coincide_rfc' => null",
            "'coincide_fecha' => null",
            "'coincide_monto' => null",
            "'coincide_moneda' => null",
            "'diferencia_monto' => null",
            "'blockingErrors' => []",
            'no los compara contra el activo, el proveedor, el folio, el UUID, la fecha, el monto, la moneda ni los valores fiscales o financieros registrados',
            'validación técnica de estructura e integridad',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        foreach ([
            'compareAgainstExpediente',
            'MONEY_TOLERANCE',
            'EXCHANGE_TOLERANCE',
            'expediente_uuid',
            'proveedor_rfc',
            '->where(\'uuid_cfdi\', $extracted[\'uuid_cfdi\'])',
            'completó automáticamente el UUID',
            'El valor fiscal no puede superar el total del CFDI asociado.',
            'El tipo de cambio debe coincidir con el CFDI',
            'blockingErrors[]',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $service);
        }
    }

    public function test_user_interfaces_explain_independent_document_support_without_misleading_matches(): void
    {
        $views = implode("\n", [
            $this->read('resources/views/swafi/expediente.blade.php'),
            $this->read('resources/views/swafi/valores.blade.php'),
            $this->read('resources/views/swafi/valores-historial.blade.php'),
            $this->read('resources/views/swafi/dashboard.blade.php'),
        ]);

        foreach ([
            'Estado técnico del XML',
            'no se comparan contra el activo, proveedor, folio, monto, moneda o valores registrados',
            'Soporte XML',
            'XML CFDI con incidencias técnicas',
        ] as $expected) {
            self::assertStringContainsString($expected, $views);
        }

        foreach ([
            '<strong>Coincidencias</strong>',
            'consistencia contra el expediente',
            'consistencia contra el XML vigente',
            'Valores sin conciliar con CFDI',
            'Conciliación CFDI',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $views);
        }
    }

    public function test_security_and_integrity_checks_for_pdf_and_xml_remain_enabled(): void
    {
        $cfdi = $this->read('app/Services/CfdiValidationService.php');
        $catalog = $this->read('app/Services/ExpedienteDocumentCatalogService.php');

        foreach ([
            'stripos($xml, \'<!DOCTYPE\')',
            'stripos($xml, \'<!ENTITY\')',
            'LIBXML_NONET',
            "'sello_presente'",
            "'certificado_presente'",
            "'timbre_presente'",
            "'hash_sha256'",
            'validateContent(UploadedFile $file)',
            "'La extensión de la imagen no coincide con su contenido real.'",
        ] as $expected) {
            self::assertStringContainsString($expected, $cfdi . $catalog);
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
