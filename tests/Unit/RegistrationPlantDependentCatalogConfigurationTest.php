<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RegistrationPlantDependentCatalogConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_controller_exposes_the_plant_relationship_for_cost_centers_and_locations(): void
    {
        $controller = $this->read('app/Http/Controllers/RegistroIndividualController.php');

        self::assertStringContainsString(
            "'centrosCosto' => \$this->catalogOptions('centros_costo', ['planta_id'])",
            $controller
        );
        self::assertStringContainsString(
            "'ubicaciones' => \$this->catalogOptions('ubicaciones', ['planta_id'])",
            $controller
        );
        self::assertStringContainsString(
            'private function catalogOptions(string $table, array $metadataColumns = [])',
            $controller
        );
        self::assertStringContainsString(
            'return $rows->map(function ($row) use ($metadataColumns): object {',
            $controller
        );
        self::assertStringContainsString('$option[$column] = $data[$column] ?? null;', $controller);
    }

    public function test_view_places_plant_first_and_marks_the_dependent_catalog_options(): void
    {
        $view = $this->read('resources/views/swafi/registro-individual.blade.php');

        $plantPosition = strpos($view, '<span>Planta o sucursal <b>*</b></span>');
        $costCenterPosition = strpos($view, '<span>Centro de costo <b>*</b></span>');
        $locationPosition = strpos($view, '<span>Ubicación física</span>');

        self::assertIsInt($plantPosition);
        self::assertIsInt($costCenterPosition);
        self::assertIsInt($locationPosition);
        self::assertLessThan($costCenterPosition, $plantPosition);
        self::assertLessThan($locationPosition, $costCenterPosition);

        foreach ([
            'data-asset-plant',
            'data-plant-dependent="centro-costo"',
            'data-plant-dependent="ubicacion"',
            'data-dependent-placeholder',
            'data-planta-id="{{ $item->planta_id }}"',
            'Seleccione primero una planta...',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
    }

    public function test_javascript_filters_and_resets_the_dependent_catalogs_by_exact_plant(): void
    {
        $script = $this->read('public/assets/swafi/js/swafi-registro-individual.js');

        foreach ([
            'const filterPlantDependentSelect = (select, plantId, emptyMessage, reset = false) =>',
            "String(option.dataset.plantaId || '') === plantId",
            'const syncPlantDependentCatalogs = ({ reset = false } = {}) =>',
            "plantSelect?.addEventListener('change'",
            'syncPlantDependentCatalogs({ reset: true });',
            'Sin centros de costo activos para esta planta.',
            'Sin ubicaciones activas para esta planta.',
            'const selectedOptionBelongsToPlant = (select, plantId) =>',
            'El centro de costo seleccionado no pertenece a la planta indicada.',
            'La ubicación seleccionada no pertenece a la planta indicada.',
        ] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
    }

    public function test_backend_rejects_a_cost_center_without_the_selected_plant_relationship(): void
    {
        $request = $this->read('app/Http/Requests/StoreRegistroIndividualRequest.php');

        foreach ([
            '$costCenter->planta_id === null',
            "(int) \$costCenter->planta_id !== (int) \$this->input('planta_id')",
            'El centro de costo seleccionado no pertenece a la planta indicada.',
            "\$query->where('planta_id', (int) \$this->input('planta_id'));",
            'La ubicación seleccionada no existe, está inactiva o no pertenece a la planta indicada.',
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_registration_permissions_remain_limited_to_administrator_and_capture_profiles(): void
    {
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        foreach ([
            "'registro-individual'",
            "'registro-individual.activo'",
            "'registro-individual.activos.buscar'",
            "'registro-individual.store'",
            "=> 'expedientes.crear'",
        ] as $expected) {
            self::assertStringContainsString($expected, $middleware);
        }

        $capturePermissions = $this->permissionBlock($seeder, 'capturaPermisos');
        $consultationPermissions = $this->permissionBlock($seeder, 'consultaPermisos');
        $plantPermissions = $this->permissionBlock($seeder, 'plantaPermisos');

        self::assertStringContainsString("'expedientes.crear'", $capturePermissions);
        self::assertStringNotContainsString("'expedientes.crear'", $consultationPermissions);
        self::assertStringNotContainsString("'expedientes.crear'", $plantPermissions);
        self::assertStringContainsString("'Administrador SWAFI'", $seeder);
    }

    private function permissionBlock(string $seeder, string $variable): string
    {
        $pattern = '/\$' . preg_quote($variable, '/') . '\s*=\s*array_merge\(\[(.*?)\],\s*\$catalogReadPermissionKeys\);/s';

        self::assertSame(1, preg_match($pattern, $seeder, $matches), $variable);

        return $matches[1];
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, $path);

        return $contents;
    }
}
