<?php

namespace Tests\Unit;

use App\Http\Controllers\ValoresActivoController;
use App\Services\SafeExceptionReporter;
use App\Services\SwafiAuthorizationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ValoresActivoControllerParsingTest extends TestCase
{
    #[DataProvider('decimalProvider')]
    public function test_parses_common_decimal_formats(string $input, int $scale, float $expected): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'toDecimal');

        $result = $method->invoke($controller, $input, $scale);

        self::assertSame($expected, $result);
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
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'parseDate');

        self::assertSame('2026-06-25', $method->invoke($controller, '25/06/2026'));
        self::assertSame('2026-06-25', $method->invoke($controller, '2026-06-25'));
        self::assertNull($method->invoke($controller, '31/02/2026'));
    }

    public function test_rejects_unknown_accounting_status_instead_of_converting_it_to_vigente(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod($controller, 'normalizeStatus');

        self::assertSame('vigente', $method->invoke($controller, 'Vigente'));
        self::assertSame('en_revision', $method->invoke($controller, 'En revisión'));
        self::assertSame('baja', $method->invoke($controller, 'Baja'));
        self::assertNull($method->invoke($controller, 'vigentee'));
    }

    private function controller(): ValoresActivoController
    {
        return new ValoresActivoController(
            new SwafiAuthorizationService(),
            new SafeExceptionReporter()
        );
    }
}
