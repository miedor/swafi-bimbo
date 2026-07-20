<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExistingAssetRegistrationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_lookup_route_is_protected_by_the_existing_registration_permission(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString("Route::get('/registro-individual/activo-existente'", $routes);
        self::assertStringContainsString("->name('registro-individual.activo')", $routes);
        self::assertStringContainsString("'registro-individual.activo'", $middleware);
        self::assertStringContainsString("=> 'expedientes.crear'", $middleware);
    }

    public function test_existing_asset_lookup_exposes_only_operational_master_data(): void
    {
        $request = $this->read('app/Http/Requests/LookupExistingAssetRequest.php');
        $service = $this->read('app/Services/AssetRegistrationService.php');

        self::assertStringContainsString("Rule::exists('activos', 'numero_activo')", $request);
        self::assertStringContainsString("->where(fn (\$query) => \$query->where('activo', true))", $request);
        self::assertStringContainsString("->where('a.activo', true)", $service);
        self::assertStringContainsString("'expedientes_vigentes'", $service);
        self::assertStringNotContainsString('monto_factura', $service);
        self::assertStringNotContainsString('uuid_cfdi', $service);
        self::assertStringNotContainsString('valor_fiscal', $service);
        self::assertStringNotContainsString('valor_financiero', $service);
    }

    public function test_registration_requires_an_explicit_new_or_existing_asset_mode(): void
    {
        $request = $this->read('app/Http/Requests/StoreRegistroIndividualRequest.php');

        foreach ([
            "'asset_mode' => ['required', Rule::in(['new', 'existing'])]",
            "Rule::unique('activos', 'numero_activo')",
            "Rule::exists('activos', 'numero_activo')",
            "return ['prohibited'];",
            'El activo ya existe. Utiliza la opción',
            'El centro de costo seleccionado no pertenece a la planta indicada.',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_existing_asset_is_locked_and_never_updated_by_individual_registration(): void
    {
        $controller = $this->read('app/Http/Controllers/RegistroIndividualController.php');
        $service = $this->read('app/Services/AssetRegistrationService.php');
        $combined = $controller . "\n" . $service;

        self::assertStringContainsString('findActiveForUpdate', $controller);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString('createNew(', $controller);
        self::assertStringContainsString("'datos_maestros_activo_modificados' => false", $controller);
        self::assertStringContainsString("'ALTA_EXPEDIENTE_INDIVIDUAL'", $controller);
        self::assertStringNotContainsString('Activo::updateOrCreate', $combined);
        self::assertStringNotContainsString("\$asset->update([", $controller);
    }

    public function test_interface_supports_search_selection_and_protected_master_fields_without_extra_pages(): void
    {
        $view = $this->read('resources/views/swafi/registro-individual.blade.php');
        $script = $this->read('public/assets/swafi/js/swafi-registro-individual.js');

        foreach ([
            'data-asset-selector',
            'data-lookup-url',
            'data-asset-mode',
            'data-asset-search',
            'data-asset-new',
            'data-asset-field',
            'data-existing-asset-notice',
            'swafi-registro-individual.js',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        foreach ([
            'fetch(url.toString()',
            "modeInput.value = 'existing'",
            'field.disabled = disabled',
            'textContent',
            'selectedAssetNumber',
        ] as $expected) {
            self::assertStringContainsString($expected, $script);
        }

        self::assertStringNotContainsString('innerHTML', $script);
        self::assertDoesNotMatchRegularExpression('/\son(click|change|submit)=/i', $view);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, $path);

        return $contents;
    }
}
