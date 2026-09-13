<?php

namespace Tests\Unit;

use App\Services\ExpedienteDocumentCatalogService;
use DomainException;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Throwable;

class Pcb011WhiteBoxTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    private ExpedienteDocumentCatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExpedienteDocumentCatalogService([
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

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * Camino 1: I -> 1 -> 2 -> 3 -> F.
     * IN: requestedType="EVIDENCIA_RECEPCION"; file=evidencia.xml.
     * OUT: DomainException porque la extensión .xml no está admitida.
     */
    public function test_camino_1_rechaza_xml_para_evidencia_de_recepcion(): void
    {
        $file = $this->file(
            'evidencia.xml',
            '<?xml version="1.0"?><Evidencia/>',
            'application/xml'
        );

        try {
            $this->service->resolveStoredType('EVIDENCIA_RECEPCION', $file);
        } catch (Throwable $exception) {
            self::assertInstanceOf(DomainException::class, $exception);
            self::assertSame(
                'El tipo de documento seleccionado no admite la extensión .xml.',
                $exception->getMessage()
            );

            return;
        }

        self::fail('Se esperaba una DomainException para la extensión .xml.');
    }

    /**
     * Camino 2: I -> 1 -> 2 -> 4 -> 5 -> F.
     * IN: requestedType="AUTO_FACTURA"; file=factura.pdf.
     * OUT: "PDF".
     */
    public function test_camino_2_clasifica_pdf_de_factura_como_pdf(): void
    {
        $file = $this->file(
            'factura.pdf',
            "%PDF-1.7\n%%EOF",
            'application/pdf'
        );

        self::assertSame(
            'PDF',
            $this->service->resolveStoredType('AUTO_FACTURA', $file)
        );
    }

    /**
     * Camino 3: I -> 1 -> 2 -> 4 -> 6 -> F.
     * IN: requestedType="EVIDENCIA_RECEPCION"; file=recepcion.pdf.
     * OUT: "EVIDENCIA_RECEPCION".
     */
    public function test_camino_3_conserva_la_clasificacion_de_evidencia_de_recepcion(): void
    {
        $file = $this->file(
            'recepcion.pdf',
            "%PDF-1.7\n%%EOF",
            'application/pdf'
        );

        self::assertSame(
            'EVIDENCIA_RECEPCION',
            $this->service->resolveStoredType('EVIDENCIA_RECEPCION', $file)
        );
    }

    private function file(
        string $name,
        string $contents,
        string $mimeType
    ): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'pcb011_');

        if (!is_string($path)) {
            self::fail('No fue posible reservar el archivo temporal de PCB-011.');
        }

        $writtenBytes = file_put_contents($path, $contents);

        if ($writtenBytes === false) {
            self::fail('No fue posible crear el archivo temporal de PCB-011.');
        }

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
