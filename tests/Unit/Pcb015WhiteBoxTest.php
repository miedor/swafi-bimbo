<?php

namespace Tests\Unit;

use App\Services\SimpleXlsxExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Pcb015WhiteBoxTest extends TestCase
{
    private SimpleXlsxExporter $exporter;

    private ReflectionMethod $looksDecimal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new SimpleXlsxExporter();
        $this->looksDecimal = new ReflectionMethod(
            SimpleXlsxExporter::class,
            'looksDecimal'
        );
    }

    /**
     * Ejecuta las cuatro filas del Paso 4 conservando el tipo PHP de cada IN.
     */
    #[DataProvider('decimalCases')]
    public function test_looks_decimal_clasifica_el_valor_esperado(
        mixed $value,
        bool $expected
    ): void {
        $result = $this->looksDecimal->invoke($this->exporter, $value);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function decimalCases(): array
    {
        return [
            'camino 1 - texto no numérico' => ['Motor SWAFI', false],
            'camino 2 - cadena numérica con punto decimal' => ['1250.50', true],
            'camino 3 - entero sin parte decimal' => [5, false],
            'camino 3 - valor de tipo float' => [5.0, true],
        ];
    }
}
