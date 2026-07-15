<?php

namespace Tests\Unit;

use App\Http\Controllers\BusquedaGuardadaController;
use App\Http\Requests\StoreBusquedaGuardadaRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SavedSearchFiltersConfigurationTest extends TestCase
{
    private const FILTER_FIELDS = [
        'folio_factura',
        'uuid_cfdi',
        'proveedor',
        'rfc',
        'numero_activo',
        'planta_id',
        'centro_costo_id',
        'area_id',
        'ubicacion_id',
        'estatus',
        'estatus_operativo',
        'fecha_desde',
        'fecha_hasta',
        'monto_desde',
        'monto_hasta',
        'ordenar_por',
        'direccion',
        'per_page',
    ];

    public function test_every_search_filter_has_an_explicit_validation_rule(): void
    {
        $rules = (new StoreBusquedaGuardadaRequest())->rules();

        self::assertArrayHasKey('nombre', $rules);
        self::assertArrayHasKey('filtros', $rules);

        foreach (self::FILTER_FIELDS as $field) {
            self::assertArrayHasKey(
                'filtros.' . $field,
                $rules,
                "El filtro {$field} debe conservarse mediante una regla de validación explícita."
            );
        }
    }

    public function test_all_filters_are_normalized_without_losing_catalog_values(): void
    {
        $controller = new BusquedaGuardadaController();
        $method = new ReflectionMethod($controller, 'normalizeFilters');
        $method->setAccessible(true);

        $normalized = $method->invoke($controller, [
            'folio_factura' => ' FAC-000184 ',
            'uuid_cfdi' => ' A1B2-C3D4 ',
            'proveedor' => ' ACME Industrial ',
            'rfc' => ' ACM010101ABC ',
            'numero_activo' => ' BIM-537028 ',
            'planta_id' => '1',
            'centro_costo_id' => '2',
            'area_id' => '3',
            'ubicacion_id' => '4',
            'estatus' => 'observado',
            'estatus_operativo' => 'en_operacion',
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-12-31',
            'monto_desde' => '100.50',
            'monto_hasta' => '900.75',
            'ordenar_por' => 'planta',
            'direccion' => 'asc',
            'per_page' => '25',
        ]);

        self::assertSame('FAC-000184', $normalized['folio_factura']);
        self::assertSame(1, $normalized['planta_id']);
        self::assertSame(2, $normalized['centro_costo_id']);
        self::assertSame(3, $normalized['area_id']);
        self::assertSame(4, $normalized['ubicacion_id']);
        self::assertSame(100.50, $normalized['monto_desde']);
        self::assertSame(900.75, $normalized['monto_hasta']);
        self::assertSame('planta', $normalized['ordenar_por']);
        self::assertSame('asc', $normalized['direccion']);
        self::assertSame(25, $normalized['per_page']);

        foreach (self::FILTER_FIELDS as $field) {
            self::assertArrayHasKey($field, $normalized);
        }
    }

    public function test_each_catalog_filter_is_recognized_as_a_meaningful_criterion(): void
    {
        $controller = new BusquedaGuardadaController();
        $method = new ReflectionMethod($controller, 'hasNoMeaningfulFilters');
        $method->setAccessible(true);

        foreach (['planta_id', 'centro_costo_id', 'area_id', 'ubicacion_id'] as $field) {
            $hasNoCriteria = $method->invoke($controller, [
                $field => 1,
                'ordenar_por' => 'fecha_factura',
                'direccion' => 'desc',
                'per_page' => 10,
            ]);

            self::assertFalse(
                $hasNoCriteria,
                "El filtro {$field} debe permitir guardar una búsqueda por sí solo."
            );
        }
    }

    public function test_view_synchronizes_live_form_values_before_saving(): void
    {
        $viewPath = dirname(__DIR__, 2) . '/resources/views/swafi/busqueda.blade.php';
        $view = file_get_contents($viewPath);

        self::assertIsString($view);
        self::assertStringContainsString('id="swafiSearchFiltersForm"', $view);
        self::assertStringContainsString('id="swafiSaveSearchForm"', $view);
        self::assertStringContainsString('data-swafi-saved-filter', $view);
        self::assertStringContainsString('new FormData(filtersForm)', $view);
        self::assertStringContainsString("savedSearchForm.addEventListener('submit'", $view);
        self::assertStringContainsString("filtersForm.addEventListener('change'", $view);
    }

    public function test_logical_deletion_behavior_remains_in_the_controller(): void
    {
        $controllerPath = dirname(__DIR__, 2) . '/app/Http/Controllers/BusquedaGuardadaController.php';
        $controller = file_get_contents($controllerPath);

        self::assertIsString($controller);
        self::assertStringContainsString('BusquedaGuardada::withTrashed()', $controller);
        self::assertStringContainsString('BUSQUEDA_GUARDADA_BAJA_LOGICA', $controller);
        self::assertStringContainsString('$busquedaData->delete();', $controller);
        self::assertStringNotContainsString('$busquedaData->forceDelete();', $controller);
    }
}
