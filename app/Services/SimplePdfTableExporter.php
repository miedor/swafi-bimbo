<?php

namespace App\Services;

class SimplePdfTableExporter
{
    private const PAGE_WIDTH = 842.0;
    private const PAGE_HEIGHT = 595.0;
    private const LEFT_MARGIN = 24.0;
    private const RIGHT_MARGIN = 24.0;
    private const TOP_MARGIN = 24.0;
    private const BOTTOM_MARGIN = 24.0;

    /**
     * Genera un PDF tabular en orientación horizontal sin dependencias externas.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     * @param array<string, mixed> $metadata
     */
    public function export(string $title, array $headers, array $rows, array $metadata = []): string
    {
        $headers = array_values($headers);
        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalized = [];

            for ($index = 0; $index < count($headers); $index++) {
                $normalized[] = $this->normalizeValue($row[$index] ?? null);
            }

            $normalizedRows[] = $normalized;
        }

        if (empty($normalizedRows)) {
            $normalizedRows[] = array_pad(['Sin resultados para los filtros seleccionados.'], count($headers), '');
        }

        $fontSize = count($headers) >= 11 ? 5.8 : (count($headers) >= 8 ? 6.5 : 7.3);
        $headerFontSize = max($fontSize, 6.2);
        $rowHeight = count($headers) >= 11 ? 13.0 : 14.0;
        $headerHeight = 20.0;
        $tableTop = self::PAGE_HEIGHT - self::TOP_MARGIN - 66.0;
        $usableHeight = $tableTop - self::BOTTOM_MARGIN - 18.0;
        $rowsPerPage = max((int) floor(($usableHeight - $headerHeight) / $rowHeight), 1);
        $pages = array_chunk($normalizedRows, $rowsPerPage);
        $columnWidths = $this->columnWidths($headers, $normalizedRows);
        $pageStreams = [];
        $pageCount = count($pages);

        foreach ($pages as $pageIndex => $pageRows) {
            $pageStreams[] = $this->pageStream(
                title: $title,
                headers: $headers,
                rows: $pageRows,
                metadata: $metadata,
                columnWidths: $columnWidths,
                pageNumber: $pageIndex + 1,
                pageCount: $pageCount,
                fontSize: $fontSize,
                headerFontSize: $headerFontSize,
                rowHeight: $rowHeight,
                headerHeight: $headerHeight,
                tableTop: $tableTop
            );
        }

        return $this->buildPdf($pageStreams);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @param array<string, mixed> $metadata
     * @param array<int, float> $columnWidths
     */
    private function pageStream(
        string $title,
        array $headers,
        array $rows,
        array $metadata,
        array $columnWidths,
        int $pageNumber,
        int $pageCount,
        float $fontSize,
        float $headerFontSize,
        float $rowHeight,
        float $headerHeight,
        float $tableTop
    ): string {
        $commands = [];
        $titleText = $this->truncate($title, 100);
        $generatedBy = $this->truncate((string) ($metadata['usuario'] ?? 'Usuario SWAFI'), 80);
        $generatedAt = (string) ($metadata['fecha'] ?? now()->format('d/m/Y H:i:s'));
        $filters = $this->truncate((string) ($metadata['filtros'] ?? 'Sin filtros adicionales'), 160);

        $commands[] = '0.07 0.20 0.36 rg';
        $commands[] = $this->textCommand($titleText, self::LEFT_MARGIN, self::PAGE_HEIGHT - 34, 15, true);
        $commands[] = '0.25 0.36 0.50 rg';
        $commands[] = $this->textCommand(
            'Generado por: ' . $generatedBy . '  |  Fecha: ' . $generatedAt,
            self::LEFT_MARGIN,
            self::PAGE_HEIGHT - 50,
            8,
            false
        );
        $commands[] = $this->textCommand('Filtros: ' . $filters, self::LEFT_MARGIN, self::PAGE_HEIGHT - 62, 7, false);

        $tableWidth = array_sum($columnWidths);
        $x = self::LEFT_MARGIN;
        $headerBottom = $tableTop - $headerHeight;

        $commands[] = '0.12 0.35 0.65 rg';
        $commands[] = sprintf('%.2F %.2F %.2F %.2F re f', $x, $headerBottom, $tableWidth, $headerHeight);
        $commands[] = '0.75 0.84 0.94 RG';
        $commands[] = '0.5 w';

        foreach ($headers as $index => $header) {
            $width = $columnWidths[$index];
            $maxChars = $this->maxCharsForWidth($width, $headerFontSize);
            $label = $this->truncate($header, $maxChars);
            $commands[] = $this->textCommand($label, $x + 2.5, $headerBottom + 7.0, $headerFontSize, true, true);
            $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $headerBottom, $x, $tableTop);
            $x += $width;
        }

        $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $headerBottom, $x, $tableTop);
        $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', self::LEFT_MARGIN, $tableTop, self::LEFT_MARGIN + $tableWidth, $tableTop);
        $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', self::LEFT_MARGIN, $headerBottom, self::LEFT_MARGIN + $tableWidth, $headerBottom);

        $yTop = $headerBottom;

        foreach ($rows as $rowIndex => $row) {
            $rowBottom = $yTop - $rowHeight;

            if ($rowIndex % 2 === 1) {
                $commands[] = '0.96 0.98 1.00 rg';
                $commands[] = sprintf('%.2F %.2F %.2F %.2F re f', self::LEFT_MARGIN, $rowBottom, $tableWidth, $rowHeight);
            }

            $commands[] = '0.08 0.17 0.28 rg';
            $commands[] = '0.82 0.87 0.93 RG';
            $x = self::LEFT_MARGIN;

            foreach ($headers as $columnIndex => $unused) {
                $width = $columnWidths[$columnIndex];
                $maxChars = $this->maxCharsForWidth($width, $fontSize);
                $text = $this->truncate((string) ($row[$columnIndex] ?? ''), $maxChars);
                $commands[] = $this->textCommand($text, $x + 2.3, $rowBottom + 4.0, $fontSize, false);
                $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $rowBottom, $x, $yTop);
                $x += $width;
            }

            $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $rowBottom, $x, $yTop);
            $commands[] = sprintf('%.2F %.2F m %.2F %.2F l S', self::LEFT_MARGIN, $rowBottom, self::LEFT_MARGIN + $tableWidth, $rowBottom);
            $yTop = $rowBottom;
        }

        $commands[] = '0.35 0.43 0.54 rg';
        $commands[] = $this->textCommand(
            'SWAFI - Sistema Web de Gestión de Facturas de Activo Fijo | Página ' . $pageNumber . ' de ' . $pageCount,
            self::LEFT_MARGIN,
            14,
            7,
            false
        );

        return implode("\n", $commands) . "\n";
    }

    /**
     * @param array<int, string> $streams
     */
    private function buildPdf(array $streams): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageObjectIds = [];
        $nextObjectId = 5;

        foreach ($streams as $stream) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R '
                . '/MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                . '/Contents ' . $contentObjectId . ' 0 R >>';

            $objects[$contentObjectId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds));
        $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 " . $objectCount . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $objectCount; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= 'trailer << /Size ' . $objectCount . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @return array<int, float>
     */
    private function columnWidths(array $headers, array $rows): array
    {
        $availableWidth = self::PAGE_WIDTH - self::LEFT_MARGIN - self::RIGHT_MARGIN;
        $weights = [];

        foreach ($headers as $index => $header) {
            $weight = max($this->stringLength($header), 8);

            foreach (array_slice($rows, 0, 30) as $row) {
                $weight = max($weight, min($this->stringLength((string) ($row[$index] ?? '')), 28));
            }

            $weights[] = min(max($weight, 8), 28);
        }

        $totalWeight = max(array_sum($weights), 1);
        $widths = [];

        foreach ($weights as $weight) {
            $widths[] = max(($availableWidth * $weight) / $totalWeight, 36.0);
        }

        $widthSum = array_sum($widths);

        if ($widthSum > $availableWidth) {
            $factor = $availableWidth / $widthSum;
            $widths = array_map(static fn (float $width): float => $width * $factor, $widths);
        }

        return $widths;
    }

    private function textCommand(
        string $text,
        float $x,
        float $y,
        float $fontSize,
        bool $bold = false,
        bool $white = false
    ): string {
        $font = $bold ? '/F2' : '/F1';
        $color = $white ? '1 1 1 rg' : '';

        return trim($color . "\nBT " . $font . ' ' . number_format($fontSize, 2, '.', '') . ' Tf '
            . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' Td ('
            . $this->pdfText($text)
            . ') Tj ET');
    }

    private function pdfText(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', ',');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);
    }

    private function truncate(string $value, int $maxCharacters): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if ($maxCharacters < 4 || $this->stringLength($value) <= $maxCharacters) {
            return $value;
        }

        return $this->stringSubstr($value, 0, $maxCharacters - 3) . '...';
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

    private function maxCharsForWidth(float $width, float $fontSize): int
    {
        return max((int) floor(($width - 5.0) / max($fontSize * 0.48, 2.2)), 4);
    }
}
