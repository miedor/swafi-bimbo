<?php

namespace Tests\Unit;

use App\Http\Controllers\ValoresActivoController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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

    public function test_unknown_accounting_status_is_preserved_for_catalog_validation_and_rejected(): void
    {
        $controller = $this->controller();
        $normalize = new ReflectionMethod($controller, 'normalizeStatus');

        self::assertSame('vigente', $normalize->invoke($controller, 'Vigente'));
        self::assertSame('en_revision', $normalize->invoke($controller, 'En revisión'));
        self::assertSame('baja', $normalize->invoke($controller, 'Baja'));
        self::assertSame('vigentee', $normalize->invoke($controller, 'vigentee'));
        self::assertNull($normalize->invoke($controller, ''));

        $validate = new ReflectionMethod($controller, 'validateImportPayload');
        $error = $validate->invoke(
            $controller,
            [
                'estatus_contable' => 'vigentee',
                'fecha_corte' => '2026-06-25',
                'vida_util_meses' => 60,
                'valor_fiscal' => 1000.0,
                'valor_financiero' => 1000.0,
                'depreciacion_acumulada' => 0.0,
                'valor_en_libros' => 1000.0,
                'moneda' => 'MXN',
                'tipo_cambio' => 1.0,
                'fecha_tipo_cambio' => null,
                'origen_tipo_cambio' => null,
                'metodo_depreciacion' => null,
                'fecha_inicio_depreciacion' => null,
                'valor_residual' => null,
            ],
            ['MXN' => false],
            ['vigente', 'en_revision', 'baja'],
            ['linea_recta']
        );

        self::assertSame('el estatus contable no existe o se encuentra inactivo.', $error);
    }

    private function controller(): ValoresActivoController
    {
        $reflection = new ReflectionClass(ValoresActivoController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        self::assertInstanceOf(ValoresActivoController::class, $controller);

        return $controller;
    }
}
