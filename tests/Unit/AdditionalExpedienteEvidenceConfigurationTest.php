<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdditionalExpedienteEvidenceConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_controlled_catalog_supports_additional_evidence_without_unsafe_extensions(): void
    {
        $config = $this->read('config/swafi.php');
        $service = $this->read('app/Services/ExpedienteDocumentCatalogService.php');

        foreach ([
            "'documentos_expediente' => [",
            "'AUTO_FACTURA' => [",
            "'EVIDENCIA_RECEPCION' => [",
            "'ACTA_ALTA' => [",
            "'ORDEN_COMPRA' => [",
            "'MANUAL_TECNICO' => [",
            "'NOTA_SOPORTE' => [",
            "'OTRO_SOPORTE' => [",
            "'category' => 'additional'",
            "['pdf', 'jpg', 'jpeg', 'png', 'webp']",
            "array_diff(\$extensions, ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp'])",
        ] as $expected) {
            self::assertStringContainsString($expected, $config . $service);
        }

        foreach (['svg', 'html', 'htm', 'js', 'exe', 'php'] as $unsafeExtension) {
            self::assertStringNotContainsString("'{$unsafeExtension}' =>", $config);
        }
    }

    public function test_form_request_authorizes_and_validates_type_files_size_names_and_content_on_server(): void
    {
        $request = $this->read('app/Http/Requests/StoreExpedienteDocumentsRequest.php');

        foreach ([
            "in_array('documentos.cargar', \$context['permissions'], true)",
            "Rule::in(\$catalog->uploadTypeKeys())",
            "'documentos' => [",
            "'array'",
            "'min:1'",
            "'documentos.*' => [",
            "'file'",
            "maxFilesPerUpload()",
            "safeMaxKilobytesFor(\$requestedType)",
            "resolveStoredType(\$requestedType, \$file)",
            "validateContent(\$file)",
            'No selecciones dos archivos con el mismo nombre en una sola carga.',
            'El nombre del archivo está vacío o supera 255 caracteres.',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_controller_preserves_versioning_storage_hash_status_and_audit_for_both_document_flows(): void
    {
        $controller = $this->read('app/Http/Controllers/DocumentoExpedienteController.php');

        foreach ([
            'StoreExpedienteDocumentsRequest $request',
            'ExpedienteDocumentCatalogService $documentTypes',
            'DB::transaction(function () use (',
            'resolveStoredType(',
            'storeUploadedDocumentFile(',
            'storeOrReplaceDocumentRecord(',
            'EVIDENCIA_ADICIONAL_AGREGADA',
            'EVIDENCIA_ADICIONAL_REEMPLAZADA',
            'DOCUMENTO_AGREGADO',
            'DOCUMENTO_REEMPLAZADO',
            "'hash_sha256' => \$resultado['hash_sha256']",
            'updateDocumentalStatus(',
            "'jpg', 'jpeg' => 'image/jpeg'",
            "'png' => 'image/png'",
            "'webp' => 'image/webp'",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }

        self::assertStringNotContainsString('->deleteFileAfterSend(false)', $controller);
        self::assertStringNotContainsString('forceDelete(', $controller);
    }

    public function test_detail_view_integrates_evidence_in_the_existing_document_tab_without_an_extra_module(): void
    {
        $view = $this->read('resources/views/swafi/expediente.blade.php');
        $controller = $this->read('app/Http/Controllers/BusquedaController.php');
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            'Agregar o reemplazar factura PDF/XML',
            'Adjuntar evidencia adicional',
            'name="tipo_documento" value="AUTO_FACTURA"',
            '<select name="tipo_documento" required>',
            '@foreach($tiposDocumentoAdicional as $documentTypeKey => $documentTypeLabel)',
            'name="documentos[]"',
            'accept="{{ $documentosAdicionalesAccept }}"',
            '$tiposDocumentoEtiquetas[$documento->tipo_documento]',
            "'tiposDocumentoAdicional' => \$this->documentTypes->additionalOptions()",
            "'tiposDocumentoEtiquetas' => \$this->documentTypes->storedTypeLabels()",
            "Route::post('/expedientes/{expediente}/documentos'",
            "'documentos.store' => 'documentos.cargar'",
        ] as $expected) {
            self::assertStringContainsString($expected, $view . $controller . $routes . $middleware);
        }

        self::assertStringNotContainsString('onclick=', $view);
        self::assertStringNotContainsString('{!! old(', $view);
        self::assertStringNotContainsString("Route::get('/evidencias-adicionales", $routes);
    }

    public function test_existing_pdf_xml_completion_rule_remains_isolated_from_additional_evidence(): void
    {
        $cfdi = $this->read('app/Services/CfdiValidationService.php');

        self::assertStringContainsString(
            "UPPER(tipo_documento) = 'PDF'",
            $cfdi
        );
        self::assertStringContainsString(
            "UPPER(tipo_documento) = 'XML'",
            $cfdi
        );
        self::assertStringNotContainsString('EVIDENCIA_RECEPCION', $cfdi);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
