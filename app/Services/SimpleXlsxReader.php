<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DomainException;
use RuntimeException;
use ZipArchive;

class SimpleXlsxReader
{
    private const MAX_ENTRIES = 250;
    private const MAX_UNCOMPRESSED_BYTES = 25_000_000;
    private const MAX_XML_BYTES = 12_000_000;
    private const MAX_COLUMNS = 64;

    /**
     * @return array<int, array<int, string>>
     */
    public function readFirstWorksheet(string $path, int $maxRows): array
    {
        if (!class_exists(DOMDocument::class) || !class_exists(DOMXPath::class)) {
            throw new DomainException(
                'El servidor no cuenta con la extensión XML requerida para leer archivos XLSX. '
                . 'Guarda el layout como CSV UTF-8 e inténtalo nuevamente.'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            throw new DomainException(
                'El servidor no cuenta con la extensión ZIP requerida para leer archivos XLSX. '
                . 'Guarda el layout como CSV UTF-8 e inténtalo nuevamente.'
            );
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new DomainException('No fue posible leer el archivo XLSX cargado.');
        }

        $zip = new ZipArchive();
        $result = $zip->open($path, ZipArchive::RDONLY);

        if ($result !== true) {
            throw new DomainException('El archivo XLSX está dañado o no corresponde a un libro válido.');
        }

        try {
            $this->assertArchiveIsSafe($zip);

            $worksheetPath = $this->resolveFirstWorksheetPath($zip);
            $worksheetXml = $this->readEntry($zip, $worksheetPath);
            $sharedStrings = $this->readSharedStrings($zip);

            return $this->parseWorksheet($worksheetXml, $sharedStrings, $maxRows);
        } finally {
            $zip->close();
        }
    }

    private function assertArchiveIsSafe(ZipArchive $zip): void
    {
        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new DomainException('El archivo XLSX contiene una estructura fuera de los límites permitidos.');
        }

        $uncompressedBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if (!is_array($stat)) {
                throw new DomainException('No fue posible validar la estructura interna del archivo XLSX.');
            }

            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));

            if (
                $name === ''
                || str_starts_with($name, '/')
                || str_contains($name, '../')
                || preg_match('/^[A-Za-z]:\//', $name) === 1
            ) {
                throw new DomainException('El archivo XLSX contiene una ruta interna no permitida.');
            }

            $uncompressedBytes += max(0, (int) ($stat['size'] ?? 0));

            if ($uncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new DomainException('El contenido descomprimido del XLSX supera el límite de seguridad permitido.');
            }
        }
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): string
    {
        $workbookXml = $this->readEntry($zip, 'xl/workbook.xml');
        $relationshipsXml = $this->readEntry($zip, 'xl/_rels/workbook.xml.rels');

        $workbook = $this->loadXml($workbookXml, 'el libro XLSX');
        $relationships = $this->loadXml($relationshipsXml, 'las relaciones del libro XLSX');

        $workbookXPath = new DOMXPath($workbook);
        $sheetNodes = $workbookXPath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]');

        if ($sheetNodes === false || $sheetNodes->length === 0) {
            throw new DomainException('El archivo XLSX no contiene hojas de cálculo.');
        }

        $sheet = $sheetNodes->item(0);

        if (!$sheet instanceof DOMElement) {
            throw new DomainException('No fue posible identificar la primera hoja del XLSX.');
        }

        $relationshipId = $sheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        );

        if ($relationshipId === '') {
            $relationshipId = $sheet->getAttribute('r:id');
        }

        if ($relationshipId === '') {
            throw new DomainException('La primera hoja del XLSX no contiene una relación válida.');
        }

        $relationshipXPath = new DOMXPath($relationships);
        $relationshipNodes = $relationshipXPath->query(
            '//*[local-name()="Relationship" and @Id=' . $this->xpathLiteral($relationshipId) . ']'
        );

        if ($relationshipNodes === false || $relationshipNodes->length === 0) {
            throw new DomainException('No fue posible resolver la ruta de la primera hoja del XLSX.');
        }

        $relationship = $relationshipNodes->item(0);

        if (!$relationship instanceof DOMElement) {
            throw new DomainException('La relación de la primera hoja del XLSX no es válida.');
        }

        $target = str_replace('\\', '/', trim($relationship->getAttribute('Target')));

        if ($target === '') {
            throw new DomainException('La primera hoja del XLSX no tiene una ruta de contenido.');
        }

        if (str_starts_with($target, '/')) {
            $target = ltrim($target, '/');
        } elseif (!str_starts_with($target, 'xl/')) {
            $target = 'xl/' . ltrim($target, '/');
        }

        $segments = [];

        foreach (explode('/', $target) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if (!str_starts_with($normalized, 'xl/worksheets/')) {
            throw new DomainException('La primera hoja del XLSX apunta a una ubicación no permitida.');
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->readEntry($zip, 'xl/sharedStrings.xml');
        $document = $this->loadXml($xml, 'las cadenas compartidas del XLSX');
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[local-name()="si"]');

        if ($nodes === false) {
            throw new DomainException('No fue posible leer las cadenas compartidas del XLSX.');
        }

        $strings = [];

        foreach ($nodes as $node) {
            $textNodes = $xpath->query('.//*[local-name()="t"]', $node);
            $value = '';

            if ($textNodes !== false) {
                foreach ($textNodes as $textNode) {
                    $value .= $textNode->textContent;
                }
            }

            $strings[] = $this->normalizeCell($value);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseWorksheet(string $xml, array $sharedStrings, int $maxRows): array
    {
        $document = $this->loadXml($xml, 'la primera hoja del XLSX');
        $xpath = new DOMXPath($document);
        $rowNodes = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');

        if ($rowNodes === false) {
            throw new DomainException('No fue posible leer las filas del archivo XLSX.');
        }

        $rows = [];
        $nonEmptyRows = 0;

        foreach ($rowNodes as $rowNode) {
            if ($nonEmptyRows > $maxRows) {
                throw new DomainException(
                    "El layout supera el máximo permitido de {$maxRows} registros para una sola importación."
                );
            }

            $cells = $xpath->query('./*[local-name()="c"]', $rowNode);
            $row = [];

            if ($cells !== false) {
                foreach ($cells as $cell) {
                    if (!$cell instanceof DOMElement) {
                        continue;
                    }

                    $formula = $xpath->query('./*[local-name()="f"]', $cell);

                    if ($formula !== false && $formula->length > 0) {
                        throw new DomainException(
                            'El archivo XLSX contiene fórmulas. Convierte las fórmulas a valores antes de importarlo.'
                        );
                    }

                    $reference = strtoupper($cell->getAttribute('r'));
                    $columnIndex = $this->columnIndexFromReference($reference);

                    if ($columnIndex < 0 || $columnIndex >= self::MAX_COLUMNS) {
                        throw new DomainException(
                            'El archivo XLSX contiene más columnas de las permitidas para un layout de SWAFI.'
                        );
                    }

                    $row[$columnIndex] = $this->cellValue($xpath, $cell, $sharedStrings);
                }
            }

            if ($row === []) {
                continue;
            }

            ksort($row);
            $lastIndex = (int) array_key_last($row);
            $normalizedRow = [];

            for ($index = 0; $index <= $lastIndex; $index++) {
                $normalizedRow[] = $this->normalizeCell($row[$index] ?? '');
            }

            if ($this->rowIsEmpty($normalizedRow)) {
                continue;
            }

            $rows[] = $normalizedRow;
            $nonEmptyRows++;
        }

        return $rows;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $nodes = $xpath->query('.//*[local-name()="is"]//*[local-name()="t"]', $cell);
            $value = '';

            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $value .= $node->textContent;
                }
            }

            return $value;
        }

        $valueNodes = $xpath->query('./*[local-name()="v"]', $cell);

        if ($valueNodes === false) {
            throw new DomainException('No fue posible leer el valor de una celda del XLSX.');
        }

        $valueNode = $valueNodes->item(0);
        $raw = $valueNode?->textContent ?? '';

        if ($type === 's') {
            $index = filter_var($raw, FILTER_VALIDATE_INT);

            if ($index === false || !array_key_exists((int) $index, $sharedStrings)) {
                throw new DomainException('El XLSX contiene una referencia de texto compartido no válida.');
            }

            return $sharedStrings[(int) $index];
        }

        if ($type === 'b') {
            return $raw === '1' ? '1' : '0';
        }

        return $raw;
    }

    private function readEntry(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);

        if (!is_array($stat)) {
            throw new DomainException("El XLSX no contiene el componente requerido: {$name}.");
        }

        $size = max(0, (int) ($stat['size'] ?? 0));

        if ($size > self::MAX_XML_BYTES) {
            throw new DomainException("El componente {$name} del XLSX supera el límite permitido.");
        }

        $contents = $zip->getFromName($name);

        if (!is_string($contents)) {
            throw new DomainException("No fue posible leer el componente {$name} del XLSX.");
        }

        return $contents;
    }

    private function loadXml(string $xml, string $description): DOMDocument
    {
        $upper = strtoupper($xml);

        if (str_contains($upper, '<!DOCTYPE') || str_contains($upper, '<!ENTITY')) {
            throw new DomainException("El contenido de {$description} incluye declaraciones XML no permitidas.");
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new DomainException("No fue posible interpretar {$description}.");
        }

        return $document;
    }

    private function columnIndexFromReference(string $reference): int
    {
        if (preg_match('/^([A-Z]+)\d+$/', $reference, $matches) !== 1) {
            throw new RuntimeException('El XLSX contiene una referencia de celda no válida.');
        }

        $index = 0;

        foreach (str_split($matches[1]) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param array<int, string> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeCell($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCell(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $normalized = str_replace("\xC2\xA0", ' ', (string) $value);
        $normalized = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $normalized) ?? '';

        return trim($normalized);
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($part !== '') {
                $segments[] = "'{$part}'";
            }

            if ($index < count($parts) - 1) {
                $segments[] = '"\'"';
            }
        }

        return 'concat(' . implode(',', $segments) . ')';
    }
}
