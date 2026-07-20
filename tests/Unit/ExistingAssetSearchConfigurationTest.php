<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExistingAssetSearchConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_paginated_search_route_is_protected_by_the_registration_permission(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString(
            "Route::get('/registro-individual/activos', [RegistroIndividualController::class, 'searchExistingAssets'])",
            $routes
        );
        self::assertStringContainsString(
            "->name('registro-individual.activos.buscar')",
            $routes
        );
        self::assertStringContainsString(
            "'registro-individual.activos.buscar'",
            $middleware
        );
        self::assertStringContainsString("=> 'expedientes.crear'", $middleware);
    }

    public function test_search_request_requires_a_safe_criterion_and_active_catalog_values(): void
    {
        $request = $this->read('app/Http/Requests/SearchExistingAssetsRequest.php');

        foreach ([
            "'required_without_all:proveedor_id,planta_id'",
            "'min:2'",
            "'max:30'",
            "'regex:/^[A-Z0-9][A-Z0-9._-]*$/'",
            "Rule::exists('proveedores', 'id')",
            "Rule::exists('plantas', 'id')",
            "->where(fn (\$query) => \$query->where('estatus', 'activo'))",
            "Rule::in([5, 8, 10, 15, 20])",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_search_is_prefix_filtered_paginated_and_avoids_n_plus_one_counts(): void
    {
        $service = $this->read('app/Services/AssetRegistrationService.php');

        foreach ([
            "->where('a.activo', true)",
            "->where('a.numero_activo', 'like', \$term . '%')",
            "->where('a.proveedor_id', (int) \$filters['proveedor_id'])",
            "->where('a.planta_id', (int) \$filters['planta_id'])",
            "->paginate(",
            "$allowedPerPage = [5, 8, 10, 15, 20]",
            "COUNT(*) AS expedientes_vigentes",
            "->whereNull('deleted_at')",
            "->leftJoinSub(",
            "COALESCE(ec.expedientes_vigentes, 0) AS expedientes_vigentes",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringNotContainsString('monto_factura', $service);
        self::assertStringNotContainsString('uuid_cfdi', $service);
        self::assertStringNotContainsString('valor_fiscal', $service);
        self::assertStringNotContainsString('valor_financiero', $service);
    }

    public function test_controller_returns_the_service_pagination_without_duplicating_query_logic(): void
    {
        $controller = $this->read('app/Http/Controllers/RegistroIndividualController.php');

        self::assertStringContainsString(
            'public function searchExistingAssets(SearchExistingAssetsRequest $request): JsonResponse',
            $controller
        );
        self::assertStringContainsString(
            '$this->assets->searchActive($request->validated())',
            $controller
        );
        self::assertStringNotContainsString("DB::table('activos as a')", $controller);
    }

    public function test_interface_keeps_search_selection_in_the_same_screen_and_renders_safely(): void
    {
        $view = $this->read('resources/views/swafi/registro-individual.blade.php');
        $script = $this->read('public/assets/swafi/js/swafi-registro-individual.js');

        foreach ([
            'data-search-url',
            'data-asset-browser',
            'data-asset-filter-query',
            'data-asset-filter-provider',
            'data-asset-filter-plant',
            'data-asset-results-body',
            'data-asset-pagination-actions',
            '¿No recuerdas el número exacto? Buscar por criterios',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        foreach ([
            'const searchAssets = async (page = 1)',
            'url.searchParams.set(\'per_page\', \'8\')',
            'resultsBody.replaceChildren()',
            'document.createElement(\'tr\')',
            'selectButton.addEventListener',
            'textContent',
            'await lookupAsset(asset.numero_activo)',
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
