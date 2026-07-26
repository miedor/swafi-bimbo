<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NewAssetRegistrationWorkflowConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_new_asset_option_produces_an_explicit_ui_state_transition(): void
    {
        $view = $this->read('resources/views/swafi/registro-individual.blade.php');
        $script = $this->read('public/assets/swafi/js/swafi-registro-individual.js');

        foreach ([
            "old('asset_mode', '')",
            'data-registration-submit',
            'data-registration-panel',
            'aria-pressed="false"',
            'Selecciona “Buscar activo existente” o “Registrar activo nuevo”',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        foreach ([
            'const activateSelectionMode = () =>',
            "modeInput.value = '';",
            'toggleAssetFields(true);',
            'setRegistrationReady(false);',
            "modeInput.value = 'new';",
            'toggleAssetFields(false);',
            'setRegistrationReady(true);',
            "newButton.addEventListener('click'",
            "activateNewMode({ clear: modeInput.value === 'existing' });",
        ] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
    }

    public function test_backend_requires_the_same_explicit_mode_selected_by_the_interface(): void
    {
        $request = $this->read('app/Http/Requests/StoreRegistroIndividualRequest.php');

        foreach ([
            "'asset_mode' => strtolower(trim((string) \$this->input('asset_mode')))",
            "'asset_mode' => ['required', Rule::in(['new', 'existing'])]",
            "return \$this->input('asset_mode') === 'new';",
            "return \$this->input('asset_mode') === 'existing';",
            "'regex:/^[A-Z0-9][A-Z0-9._-]*$/'",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }

        self::assertStringNotContainsString("input('asset_mode', 'new')", $request);
    }

    public function test_registration_routes_remain_restricted_to_administrator_and_capture_profiles(): void
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

        self::assertStringContainsString(
            "foreach (\$allPermissionIds as \$permissionId)",
            $seeder
        );
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
