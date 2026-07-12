<?php

namespace Tests\Unit;

use App\Http\Controllers\ValoresActivoController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValoresActivoControllerParsingTest extends TestCase
{
    #[DataProvider('decimalProvider')]
    public function test_parses_common_decimal_formats(string $input, int $scale, float $expected): void
    {
        $controller = new ValoresActivoController();
        $method = new \ReflectionMethod($controller, 'toDecimal');

        $result = $method->invoke($controller, $input, $scale);

        $this->assertSame($expected, $result);
    }

    public static function decimalProvider(): array
    {
        return [
            'mexican thousands and decimal point' => ['1,234.56', 2, 1234.56],
            'european thousands and decimal comma' => ['1.234,56', 2, 1234.56],
            'decimal comma without thousands' => ['1234,56', 2, 1234.56],
            'thousands comma' => ['1,234', 2, 1234.00],
            'exchange rate with decimal comma' => ['17,123456', 6, 17.123456],
        ];
    }

    public function test_validates_dates_without_normalizing_invalid_calendar_days(): void
    {
        $controller = new ValoresActivoController();
        $method = new \ReflectionMethod($controller, 'parseDate');

        $this->assertSame('2026-06-25', $method->invoke($controller, '25/06/2026'));
        $this->assertSame('2026-06-25', $method->invoke($controller, '2026-06-25'));
        $this->assertNull($method->invoke($controller, '31/02/2026'));
    }

    public function test_rejects_unknown_accounting_status_instead_of_converting_it_to_vigente(): void
    {
        $controller = new ValoresActivoController();
        $method = new \ReflectionMethod($controller, 'normalizeStatus');

        $this->assertSame('vigente', $method->invoke($controller, 'Vigente'));
        $this->assertSame('en_revision', $method->invoke($controller, 'En revisión'));
        $this->assertSame('baja', $method->invoke($controller, 'Baja'));
        $this->assertNull($method->invoke($controller, 'vigentee'));
    }
}
