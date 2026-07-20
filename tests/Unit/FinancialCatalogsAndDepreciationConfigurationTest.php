<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FinancialCatalogsAndDepreciationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_creates_catalogs_reference_fields_indexes_and_foreign_keys(): void
    {
        $migration = $this->read('database/migrations/2026_07_20_000590_create_financial_catalogs_and_depreciation_fields.php');

        foreach ([
            "Schema::create('monedas'",
            "Schema::create('estatus_contables'",
            "'metodo_depreciacion'",
            "'fecha_inicio_depreciacion'",
            "'valor_residual'",
            "'depreciacion_estimada'",
            "'valor_en_libros_estimado'",
            "'calculo_depreciacion_at'",
            "'valores_activo_depreciacion_ref_idx'",
            "'valores_activo_moneda_catalog_fk'",
            "'valores_activo_estatus_contable_catalog_fk'",
            "'expedientes_moneda_catalog_fk'",
            "'HABILITA_DEPRECIACION_REFERENCIAL'",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }

        self::assertStringContainsString('restrictOnDelete()', $migration);
        self::assertStringContainsString('cascadeOnUpdate()', $migration);
        self::assertStringContainsString('public function down(): void', $migration);
    }

    public function test_server_validation_uses_active_financial_catalogs_and_complete_reference_rules(): void
    {
        $request = $this->read('app/Http/Requests/StoreValorActivoRequest.php');
        $filterRequest = $this->read('app/Http/Requests/FilterValoresActivoRequest.php');

        foreach ([
            "Rule::exists('monedas', 'clave')",
            "Rule::exists('estatus_contables', 'clave')",
            "Rule::in(\$methods)",
            "'fecha_inicio_depreciacion'",
            "'valor_residual'",
            'El valor residual no puede ser mayor que el valor financiero',
            'La fecha de inicio de depreciación no puede ser posterior a la fecha de corte',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }

        self::assertStringContainsString("'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde']", $filterRequest);
        self::assertStringContainsString("'valor_hasta' => ['nullable', 'numeric', 'gte:valor_desde'", $filterRequest);
        self::assertStringContainsString("Rule::exists('monedas', 'clave')", $filterRequest);
    }

    public function test_values_controller_recalculates_reference_on_server_and_exports_it(): void
    {
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');

        foreach ([
            'DepreciacionReferencialService',
            'resolveDepreciationReference',
            "'depreciacion_estimada'",
            "'valor_en_libros_estimado'",
            "'calculo_depreciacion_at'",
            "'Metodo depreciacion'",
            "'Depreciacion estimada'",
            "'Valor en libros estimado'",
            "'metodosDepreciacion'",
            "DB::table('monedas')",
            "DB::table('estatus_contables')",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }

        self::assertStringNotContainsString("['vigente', 'en_revision', 'baja']", $controller);
        self::assertStringNotContainsString('$payload[\'moneda\'] !== \'MXN\'', $controller);
    }

    public function test_primary_forms_use_catalogs_instead_of_fixed_currency_options(): void
    {
        $registration = $this->read('resources/views/swafi/registro-individual.blade.php');
        $edit = $this->read('resources/views/swafi/expediente-editar.blade.php');
        $values = $this->read('resources/views/swafi/valores.blade.php');
        $reports = $this->read('resources/views/swafi/reportes.blade.php');
        $bulkRegistration = $this->read('app/Services/RegistroMasivoService.php');

        foreach ([$registration, $edit] as $view) {
            self::assertStringContainsString('@foreach($monedas as $moneda)', $view);
            self::assertStringNotContainsString('<option value="USD"', $view);
            self::assertStringNotContainsString('<option value="EUR"', $view);
        }

        foreach ([
            "@foreach(\$catalogos['monedas'] as \$moneda)",
            "@foreach(\$catalogos['estatusContables'] as \$estatus)",
            "@foreach(\$catalogos['metodosDepreciacion'] as \$clave => \$metodo)",
            'data-vf-calculate-reference',
            'no sustituye la depreciación oficial',
            'textContent',
        ] as $expected) {
            self::assertStringContainsString($expected, $values);
        }

        self::assertStringNotContainsString('innerHTML', $values);
        self::assertDoesNotMatchRegularExpression('/\son(click|change|submit)=/i', $values);
        self::assertStringContainsString("@foreach(\$catalogos['estatusContables'] as \$estatusContable)", $reports);
        self::assertStringNotContainsString('<option value="en_revision"', $reports);
        self::assertStringContainsString("DB::table('monedas')", $bulkRegistration);
        self::assertStringNotContainsString("in_array(\$value, ['MXN', 'USD', 'EUR']", $bulkRegistration);
    }

    public function test_reference_values_are_available_in_history_detail_exports_and_reports(): void
    {
        foreach ([
            'app/Services/ValorActivoFichaService.php',
            'app/Services/ValorActivoHistoryService.php',
            'app/Http/Controllers/ValorActivoHistoryController.php',
            'app/Http/Controllers/ReportesController.php',
            'resources/views/swafi/valores-historial.blade.php',
            'resources/views/swafi/expediente.blade.php',
            'resources/views/swafi/reportes.blade.php',
        ] as $path) {
            $contents = $this->read($path);
            self::assertStringContainsString('depreciacion_estimada', $contents, $path);
            self::assertStringContainsString('valor_en_libros_estimado', $contents, $path);
        }
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}
