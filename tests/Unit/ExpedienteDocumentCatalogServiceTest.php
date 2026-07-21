<?php

namespace Tests\Unit;

use App\Services\ExpedienteDocumentCatalogService;
use DomainException;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ExpedienteDocumentCatalogServiceTest extends TestCase
{
    /** @var array<int,string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_base_mode_resolves_pdf_and_xml_after_content_validation(): void
    {
        $service = $this->service();
        $pdf = $this->file('factura.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF", 'application/pdf');
        $xml = $this->file(
            'cfdi.xml',
            '<?xml version="1.0"?><Comprobante Version="4.0"/>',
            'application/xml'
        );

        self::assertSame('PDF', $service->resolveStoredType('AUTO_FACTURA', $pdf));
        self::assertSame('XML', $service->resolveStoredType('AUTO_FACTURA', $xml));

        $service->validateContent($pdf);
        $service->validateContent($xml);

        self::assertTrue(true);
    }

    public function test_additional_options_exclude_base_and_keep_readable_stored_labels(): void
    {
        $service = $this->service();

        self::assertSame(
            ['EVIDENCIA_RECEPCION' => 'Evidencia de recepción'],
            $service->additionalOptions()
        );
        self::assertSame('Factura PDF', $service->storedTypeLabels()['PDF']);
        self::assertSame('CFDI XML', $service->storedTypeLabels()['XML']);
        self::assertSame(
            'Evidencia de recepción',
            $service->storedTypeLabels()['EVIDENCIA_RECEPCION']
        );
        self::assertSame('.pdf,.jpg,.jpeg,.png,.webp', $service->additionalAcceptAttribute());
    }

    public function test_selected_type_rejects_an_extension_outside_its_catalog_rule(): void
    {
        $service = $this->service();
        $xml = $this->file(
            'evidencia.xml',
            '<?xml version="1.0"?><Evidencia/>',
            'application/xml'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no admite la extensión .xml');

        $service->resolveStoredType('EVIDENCIA_RECEPCION', $xml);
    }

    public function test_fake_pdf_content_is_rejected_even_when_the_extension_is_pdf(): void
    {
        $service = $this->service();
        $file = $this->file('evidencia.pdf', '<html>no es pdf</html>', 'application/pdf');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no corresponde a un PDF válido');

        $service->validateContent($file);
    }

    public function test_xml_with_external_entity_declaration_is_rejected(): void
    {
        $service = $this->service();
        $file = $this->file(
            'cfdi.xml',
            '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY y SYSTEM "file:///etc/passwd">]><x>&y;</x>',
            'application/xml'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('declaraciones externas no permitidas');

        $service->validateContent($file);
    }

    public function test_real_png_is_accepted_and_mismatched_image_extension_is_rejected(): void
    {
        $service = $this->service();
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z9ZkAAAAASUVORK5CYII=',
            true
        );

        self::assertIsString($pngBytes);

        $png = $this->file('evidencia.png', $pngBytes, 'image/png');
        $service->validateContent($png);
        self::assertSame(
            'EVIDENCIA_RECEPCION',
            $service->resolveStoredType('EVIDENCIA_RECEPCION', $png)
        );

        $mismatched = $this->file('evidencia.jpg', $pngBytes, 'image/jpeg');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no coincide con su contenido real');

        $service->validateContent($mismatched);
    }

    private function service(): ExpedienteDocumentCatalogService
    {
        return new ExpedienteDocumentCatalogService([
            'AUTO_FACTURA' => [
                'label' => 'Factura PDF/XML',
                'category' => 'base',
                'extensions' => ['pdf', 'xml'],
                'max_kb' => 20480,
            ],
            'EVIDENCIA_RECEPCION' => [
                'label' => 'Evidencia de recepción',
                'category' => 'additional',
                'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
                'max_kb' => 10240,
            ],
        ], 10);
    }

    private function file(string $name, string $contents, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'swafi_document_test_');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile(
            $path,
            $name,
            $mimeType,
            null,
            true
        );
    }
}
