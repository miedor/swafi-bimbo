<?php

namespace App\Services;

use DomainException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ExpedienteDocumentCatalogService
{
    public const BASE_UPLOAD_TYPE = 'AUTO_FACTURA';

    /** @var array<string,array{label:string,category:string,extensions:array<int,string>,max_kb:int}> */
    private array $definitions;

    private int $maxFilesPerUpload;

    /**
     * @param array<string,array{label:string,category:string,extensions:array<int,string>,max_kb:int}>|null $definitions
     */
    public function __construct(?array $definitions = null, ?int $maxFilesPerUpload = null)
    {
        $configuration = [];

        if ($definitions === null || $maxFilesPerUpload === null) {
            $configuration = $this->loadConfiguration();
        }

        $this->definitions = $definitions
            ?? (array) ($configuration['tipos'] ?? []);
        $this->maxFilesPerUpload = $maxFilesPerUpload
            ?? (int) ($configuration['max_archivos_por_carga'] ?? 10);

        $this->assertValidConfiguration();
    }

    /** @return array<string,mixed> */
    private function loadConfiguration(): array
    {
        $container = Container::getInstance();

        if (!$container->bound('config')) {
            return [];
        }

        $repository = $container->make('config');

        if (!$repository instanceof Repository) {
            return [];
        }

        $configuration = $repository->get('swafi.documentos_expediente', []);

        return is_array($configuration) ? $configuration : [];
    }

    /** @return array<int,string> */
    public function uploadTypeKeys(): array
    {
        return array_keys($this->definitions);
    }

    /** @return array<string,string> */
    public function additionalOptions(): array
    {
        $options = [];

        foreach ($this->definitions as $key => $definition) {
            if ($definition['category'] !== 'additional') {
                continue;
            }

            $options[$key] = $definition['label'];
        }

        return $options;
    }

    /** @return array<string,string> */
    public function storedTypeLabels(): array
    {
        $labels = [
            'PDF' => 'Factura PDF',
            'XML' => 'CFDI XML',
        ];

        foreach ($this->definitions as $key => $definition) {
            if ($key === self::BASE_UPLOAD_TYPE) {
                continue;
            }

            $labels[$key] = $definition['label'];
        }

        return $labels;
    }

    public function additionalAcceptAttribute(): string
    {
        $extensions = [];

        foreach ($this->definitions as $definition) {
            if ($definition['category'] !== 'additional') {
                continue;
            }

            foreach ($definition['extensions'] as $extension) {
                $extensions[] = '.' . strtolower($extension);
            }
        }

        return implode(',', array_values(array_unique($extensions)));
    }

    public function maxFilesPerUpload(): int
    {
        return $this->maxFilesPerUpload;
    }

    public function maxKilobytesFor(string $requestedType): int
    {
        return $this->definition($requestedType)['max_kb'];
    }

    public function safeMaxKilobytesFor(?string $requestedType): int
    {
        try {
            return $this->maxKilobytesFor(
                $this->normalizeRequestedType($requestedType)
            );
        } catch (DomainException) {
            return $this->definitions[self::BASE_UPLOAD_TYPE]['max_kb'];
        }
    }

    public function normalizeRequestedType(?string $requestedType): string
    {
        $normalized = strtoupper(trim((string) $requestedType));

        return $normalized !== ''
            ? $normalized
            : self::BASE_UPLOAD_TYPE;
    }

    public function isAdditional(string $requestedType): bool
    {
        return $this->definition($requestedType)['category'] === 'additional';
    }

    public function resolveStoredType(string $requestedType, UploadedFile $file): string
    {
        $requestedType = $this->normalizeRequestedType($requestedType);
        $definition = $this->definition($requestedType);
        $extension = $this->fileExtension($file);

        if (!in_array($extension, $definition['extensions'], true)) {
            throw new DomainException(
                'El tipo de documento seleccionado no admite la extensión .' . $extension . '.'
            );
        }

        return $requestedType === self::BASE_UPLOAD_TYPE
            ? strtoupper($extension)
            : $requestedType;
    }

    public function validateContent(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new DomainException('El archivo no terminó de cargarse correctamente.');
        }

        $path = $file->getRealPath();

        if (!$path || !is_file($path) || !is_readable($path)) {
            throw new DomainException('No fue posible leer el archivo temporal cargado.');
        }

        $extension = $this->fileExtension($file);

        if ($extension === 'pdf') {
            $this->validatePdf($path);

            return;
        }

        if ($extension === 'xml') {
            $this->validateXml($path);

            return;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->validateImage($path, $extension);

            return;
        }

        throw new DomainException('La extensión del archivo no está permitida.');
    }

    /** @return array{label:string,category:string,extensions:array<int,string>,max_kb:int} */
    private function definition(string $requestedType): array
    {
        $normalized = $this->normalizeRequestedType($requestedType);
        $definition = $this->definitions[$normalized] ?? null;

        if (!is_array($definition)) {
            throw new DomainException('El tipo de documento seleccionado no está disponible.');
        }

        return $definition;
    }

    private function fileExtension(UploadedFile $file): string
    {
        $extension = strtolower(trim($file->getClientOriginalExtension()));

        if ($extension === '') {
            $extension = strtolower((string) pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_EXTENSION
            ));
        }

        if ($extension === '') {
            throw new DomainException('El archivo debe incluir una extensión permitida.');
        }

        return $extension;
    }

    private function validatePdf(string $path): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new DomainException('No fue posible validar el contenido del PDF.');
        }

        try {
            $prefix = fread($handle, 1024);
        } finally {
            fclose($handle);
        }

        if (!is_string($prefix) || !str_contains($prefix, '%PDF-')) {
            throw new DomainException(
                'El archivo tiene extensión PDF, pero su contenido no corresponde a un PDF válido.'
            );
        }
    }

    private function validateXml(string $path): void
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new RuntimeException(
                'La extensión DOM de PHP es necesaria para validar documentos XML.'
            );
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || trim($contents) === '') {
            throw new DomainException('El archivo XML está vacío o no puede leerse.');
        }

        $upperContents = strtoupper($contents);

        if (
            str_contains($upperContents, '<!DOCTYPE')
            || str_contains($upperContents, '<!ENTITY')
        ) {
            throw new DomainException(
                'El archivo XML contiene declaraciones externas no permitidas.'
            );
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML(
                $contents,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT
            );
            $hasErrors = libxml_get_errors() !== [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || $hasErrors || !$document->documentElement) {
            throw new DomainException('La estructura del archivo XML no es válida.');
        }
    }

    private function validateImage(string $path, string $extension): void
    {
        $image = @getimagesize($path);

        if (!is_array($image) || !isset($image[2])) {
            throw new DomainException(
                'El archivo tiene una extensión de imagen, pero su contenido no es una imagen válida.'
            );
        }

        $expectedType = match ($extension) {
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1,
            default => -1,
        };

        if ((int) $image[2] !== $expectedType) {
            throw new DomainException(
                'La extensión de la imagen no coincide con su contenido real.'
            );
        }
    }

    private function assertValidConfiguration(): void
    {
        if (!isset($this->definitions[self::BASE_UPLOAD_TYPE])) {
            throw new RuntimeException(
                'La configuración de documentos debe incluir el tipo AUTO_FACTURA.'
            );
        }

        if ($this->maxFilesPerUpload < 1 || $this->maxFilesPerUpload > 20) {
            throw new RuntimeException(
                'El límite de archivos por carga debe estar entre 1 y 20.'
            );
        }

        foreach ($this->definitions as $key => $definition) {
            if (!preg_match('/^[A-Z0-9_]{2,30}$/', (string) $key)) {
                throw new RuntimeException('La clave de tipo documental no es válida.');
            }

            $label = trim((string) ($definition['label'] ?? ''));
            $category = (string) ($definition['category'] ?? '');
            $extensions = array_values(array_unique(array_map(
                static fn ($extension): string => strtolower(trim((string) $extension)),
                (array) ($definition['extensions'] ?? [])
            )));
            $maxKilobytes = (int) ($definition['max_kb'] ?? 0);

            if ($label === '' || !in_array($category, ['base', 'additional'], true)) {
                throw new RuntimeException('La definición de tipo documental está incompleta.');
            }

            if ($extensions === [] || array_diff($extensions, ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp']) !== []) {
                throw new RuntimeException('La definición contiene extensiones no permitidas.');
            }

            if ($maxKilobytes < 1 || $maxKilobytes > 20480) {
                throw new RuntimeException('El tamaño máximo por archivo debe estar entre 1 KB y 20 MB.');
            }

            $this->definitions[$key] = [
                'label' => $label,
                'category' => $category,
                'extensions' => $extensions,
                'max_kb' => $maxKilobytes,
            ];
        }
    }
}
