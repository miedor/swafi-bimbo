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
            ],
            ['MXN' => false],
            ['vigente', 'en_revision', 'baja']
        );

        self::assertSame('el estatus contable no existe o se encuentra inactivo.', $error);
    }

    public function test_oracle_values_are_not_recalculated_or_cross_compared(): void
    {
        $controller = $this->controller();
        $validate = new ReflectionMethod($controller, 'validateImportPayload');

        $error = $validate->invoke(
            $controller,
            [
                'estatus_contable' => 'vigente',
                'fecha_corte' => '2026-06-25',
                'vida_util_meses' => 60,
                'valor_fiscal' => 1000.0,
                'valor_financiero' => 1000.0,
                'depreciacion_acumulada' => 1200.0,
                'valor_en_libros' => 1500.0,
                'moneda' => 'MXN',
                'tipo_cambio' => 1.0,
                'fecha_tipo_cambio' => null,
                'origen_tipo_cambio' => null,
            ],
            ['MXN' => false],
            ['vigente', 'en_revision', 'baja']
        );

        self::assertNull($error, 'SWAFI debe resguardar los valores oficiales de Oracle ERP sin recalcularlos.');
    }

    public function test_downloaded_template_headers_match_the_import_validator(): void
    {
        $controller = $this->controller();
        $reflection = new ReflectionClass($controller);
        $templateHeaders = $reflection->getConstant('IMPORT_TEMPLATE_HEADERS');
        $requiredHeaders = $reflection->getConstant('IMPORT_REQUIRED_HEADERS');
        $normalize = new ReflectionMethod($controller, 'normalizeImportHeaders');

        self::assertIsArray($templateHeaders);
        self::assertIsArray($requiredHeaders);

        $normalized = $normalize->invoke($controller, array_values($templateHeaders));

        self::assertSame(
            [],
            array_values(array_diff($requiredHeaders, $normalized)),
            'La plantilla descargada debe contener todos los encabezados obligatorios que valida la carga masiva.'
        );
    }

    public function test_previous_official_template_headers_remain_compatible(): void
    {
        $controller = $this->controller();
        $normalize = new ReflectionMethod($controller, 'normalizeImportHeaders');

        $normalized = $normalize->invoke($controller, [
            'Numero activo',
            'Valor fiscal',
            'Depreciacion acumulada Oracle ERP',
            'Valor en libros Oracle ERP',
            'Valor financiero',
            'Moneda',
            'Tipo cambio',
            'Fecha tipo cambio',
            'Origen tipo cambio',
            'Vida util oficial meses',
            'Fecha corte',
            'Estatus contable',
            'Motivo cambio',
        ]);

        self::assertContains('depreciacion_acumulada', $normalized);
        self::assertContains('valor_en_libros', $normalized);
        self::assertContains('vida_util_meses', $normalized);
        self::assertNotContains('depreciacion_acumulada_oracle_erp', $normalized);
        self::assertNotContains('valor_en_libros_oracle_erp', $normalized);
        self::assertNotContains('vida_util_oficial_meses', $normalized);
    }

    private function controller(): ValoresActivoController
    {
        $reflection = new ReflectionClass(ValoresActivoController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        self::assertInstanceOf(ValoresActivoController::class, $controller);

        return $controller;
    }
}
