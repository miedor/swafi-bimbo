<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InitialAssetLocationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_route_and_middleware_protect_the_initial_location_action(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            "Route::post('/expedientes/{expediente}/ubicacion-inicial'",
            "InitialAssetLocationController::class, 'store'",
            "->name('expedientes.ubicacion-inicial')",
            "'expedientes.ubicacion-inicial' => 'ubicaciones.administrar'",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes.$middleware);
        }
    }

    public function test_request_validates_authorization_catalogs_dates_and_traceability_fields(): void
    {
        $request = $this->read('app/Http/Requests/ConfirmInitialAssetLocationRequest.php');

        foreach ([
            "in_array('ubicaciones.administrar', \$context['permissions'], true)",
            "Rule::exists('ubicaciones', 'id')",
            "Rule::exists('responsables', 'id')",
            "'before_or_equal:today'",
            "'motivo' => [",
            "'min:10'",
            "'max:500'",
            "'evidencia' => [",
            "'max:2000'",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_service_blocks_reassignment_and_registers_an_initial_movement_with_audit(): void
    {
        $service = $this->read('app/Services/InitialAssetLocationService.php');

        foreach ([
            'DB::transaction(',
            'lockForUpdate()',
            'El activo ya cuenta con una ubicación actual.',
            'El activo ya tiene historial de movimientos.',
            'assertMovementAllowed(',
            "'ubicacion_destino_id' => 'ubicacion_id'",
            "'fecha_movimiento' => 'fecha_asignacion'",
            'La ubicación inicial debe pertenecer a la misma planta registrada para el activo.',
            "'ubicacion_origen_id' => null",
            "'UBICACION_INICIAL_CONFIRMADA'",
            "'tipo_asignacion' => 'inicial'",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringNotContainsString('->delete()', $service);
        self::assertStringNotContainsString('forceDelete(', $service);
    }

    public function test_detail_view_integrates_the_initial_assignment_without_creating_an_extra_module(): void
    {
        $view = $this->read('resources/views/swafi/expediente.blade.php');
        $controller = $this->read('app/Http/Controllers/BusquedaController.php');

        foreach ([
            'Confirmar ubicación inicial desde este expediente',
            "route('expedientes.ubicacion-inicial', \$expediente->expediente_id)",
            'name="ubicacion_id"',
            'name="responsable_id"',
            'name="fecha_asignacion"',
            'name="motivo"',
            'name="evidencia"',
            '@disabled($ubicacionesIniciales->isEmpty())',
            "'ubicacionesIniciales' => \$locationOptions['ubicaciones']",
            "'responsablesUbicacion' => \$locationOptions['responsables']",
            "if (\$detalle->ubicacion_id === null)",
        ] as $expected) {
            self::assertStringContainsString($expected, $view.$controller);
        }

        self::assertStringNotContainsString('{!! old(', $view);
        self::assertStringNotContainsString('onclick=', $view);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
