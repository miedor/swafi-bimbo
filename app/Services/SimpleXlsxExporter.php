<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SimpleXlsxExporter
{
    private const REQUIRED_ARCHIVE_ENTRIES = [
        '[Content_Types].xml',
        '_rels/.rels',
        'docProps/app.xml',
        'docProps/core.xml',
        'xl/workbook.xml',
        'xl/_rels/workbook.xml.rels',
        'xl/styles.xml',
        'xl/worksheets/sheet1.xml',
    ];

    /**
     * Genera un archivo XLSX básico sin dependencias externas.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    public function export(string $sheetName, array $headers, array $rows): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP de PHP no está disponible para generar el archivo Excel.');
        }

        if ($headers === []) {
            throw new RuntimeException('El archivo Excel requiere al menos una columna.');
        }

        $directory = $this->temporaryDirectory();
        $path = $directory . DIRECTORY_SEPARATOR . 'swafi_' . bin2hex(random_bytes(16)) . '.xlsx';
        $zip = new ZipArchive();
        $opened = false;

        try {
            $openResult = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($openResult !== true) {
                throw new RuntimeException('No fue posible crear el archivo Excel temporal. Código ZIP: ' . (string) $openResult . '.');
            }

            $opened = true;
            $safeSheetName = $this->safeSheetName($sheetName);
            $sheetXml = $this->worksheetXml($headers, $rows);

            $this->addZipEntry($zip, '[Content_Types].xml', $this->contentTypesXml());
            $this->addZipEntry($zip, '_rels/.rels', $this->rootRelationshipsXml());
            $this->addZipEntry($zip, 'docProps/app.xml', $this->appPropertiesXml($safeSheetName));
            $this->addZipEntry($zip, 'docProps/core.xml', $this->corePropertiesXml());
            $this->addZipEntry($zip, 'xl/workbook.xml', $this->workbookXml($safeSheetName));
            $this->addZipEntry($zip, 'xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $this->addZipEntry($zip, 'xl/styles.xml', $this->stylesXml());
            $this->addZipEntry($zip, 'xl/worksheets/sheet1.xml', $sheetXml);

            if (!$zip->close()) {
                $opened = false;
                throw new RuntimeException('No fue posible finalizar el archivo Excel temporal.');
            }

            $opened = false;

            if (!is_file($path) || !is_readable($path) || (int) filesize($path) < 100) {
                throw new RuntimeException('El archivo Excel temporal no fue generado correctamente.');
            }

            $this->validateArchive($path);

            return $path;
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }

            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }
    }

    /**
     * Genera el XLSX en memoria y elimina siempre el archivo temporal.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    public function exportBytes(string $sheetName, array $headers, array $rows): string
    {
        $path = $this->export($sheetName, $headers, $rows);

        try {
            $contents = file_get_contents($path);

            if (!is_string($contents) || $contents === '') {
                throw new RuntimeException('No fue posible leer el archivo Excel generado.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function temporaryDirectory(): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $directory = $base . DIRECTORY_SEPARATOR . 'swafi_exports';

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio temporal de exportaciones.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('El directorio temporal de exportaciones no tiene permisos de escritura.');
        }

        return $directory;
    }

    private function addZipEntry(ZipArchive $zip, string $entryName, string $contents): void
    {
        if (!$zip->addFromString($entryName, $contents)) {
            throw new RuntimeException("No fue posible agregar {$entryName} al archivo Excel.");
        }
    }

    private function validateArchive(string $path): void
    {
        $validationZip = new ZipArchive();
        $openResult = $validationZip->open($path, ZipArchive::RDONLY);

        if ($openResult !== true) {
            throw new RuntimeException('El archivo Excel generado no pudo abrirse para su validación.');
        }

        try {
            foreach (self::REQUIRED_ARCHIVE_ENTRIES as $entryName) {
                if ($validationZip->locateName($entryName) === false) {
                    throw new RuntimeException("El archivo Excel generado está incompleto: falta {$entryName}.");
                }
            }
        } finally {
            $validationZip->close();
        }
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>'
            . '<sheets><sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<calcPr calcId="191029"/>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function appPropertiesXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>SWAFI</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant">'
            . '<vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>'
            . '<vt:variant><vt:i4>1</vt:i4></vt:variant>'
            . '</vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xml($sheetName) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>Bimbo S.A. de C.V.</Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    private function corePropertiesXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Reporte SWAFI</dc:title>'
            . '<dc:creator>SWAFI</dc:creator>'
            . '<cp:lastModifiedBy>SWAFI</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F5AA6"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    private function worksheetXml(array $headers, array $rows): string
    {
        $allRows = [$headers, ...$rows];
        $columnWidths = $this->columnWidths($allRows, count($headers));
        $lastColumn = $this->columnLetter(max(count($headers), 1));
        $lastRow = max(count($allRows), 1);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>';
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<cols>';

        foreach ($columnWidths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';

        foreach ($allRows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $rowHeight = $rowIndex === 0 ? 28 : 20;
            $xml .= '<row r="' . $excelRow . '" ht="' . $rowHeight . '" customHeight="1">';

            for ($columnIndex = 0; $columnIndex < count($headers); $columnIndex++) {
                $value = $row[$columnIndex] ?? null;
                $cellReference = $this->columnLetter($columnIndex + 1) . $excelRow;
                $style = $rowIndex === 0 ? 1 : (is_numeric($value) && $this->looksDecimal($value) ? 2 : 0);

                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $cellReference . '" s="' . $style . '"/>';
                    continue;
                }

                if ($rowIndex > 0 && is_numeric($value) && !is_string($value)) {
                    $xml .= '<c r="' . $cellReference . '" s="' . $style . '"><v>' . $this->xml((string) $value) . '</v></c>';
                    continue;
                }

                $text = $this->sanitizeText((string) $value);
                $xml .= '<c r="' . $cellReference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->xml($text)
                    . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>';
        $xml .= '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
        $xml .= '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, float>
     */
    private function columnWidths(array $rows, int $columnCount): array
    {
        $widths = array_fill(0, $columnCount, 12.0);

        foreach ($rows as $row) {
            for ($index = 0; $index < $columnCount; $index++) {
                $length = $this->stringLength((string) ($row[$index] ?? ''));
                $widths[$index] = min(max($widths[$index], $length + 2), 42);
            }
        }

        return $widths;
    }

    private function columnLetter(int $column): string
    {
        $letters = '';

        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }

        return $letters;
    }

    private function safeSheetName(string $name): string
    {
        /*
        |--------------------------------------------------------------------------
        | Nombre seguro de la hoja
        |--------------------------------------------------------------------------
        | Excel no permite los caracteres \ / ? * [ ] : en el nombre de una hoja.
        | Se usa str_replace en lugar de una expresión regular para evitar que el
        | carácter "/" pueda interpretarse accidentalmente como delimitador.
        */
        $name = str_replace(
            ['\\', '/', '?', '*', '[', ']', ':'],
            ' ',
            trim($name)
        );

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B'");

        if ($name === '') {
            $name = 'Reporte';
        }

        $name = $this->stringSubstr($name, 0, 31);
        $name = rtrim($name, " '");

        return $name !== '' ? $name : 'Reporte';
    }

    private function sanitizeText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return $this->stringSubstr($value, 0, 32767);
    }

    private function looksDecimal(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        return str_contains((string) $value, '.') || is_float($value);
    }


    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function stringSubstr(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length)
            : substr($value, $start, $length);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
