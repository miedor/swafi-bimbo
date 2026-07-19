<?php

namespace Tests\Unit;

use App\Http\Controllers\EtiquetaActivoController;
use App\Http\Controllers\ReportesController;
use App\Http\Middleware\SwafiAuth;
use App\Services\AssetStatusCatalogService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class InventoryStoriesConfigurationTest extends TestCase
{
    public function test_pending_inventory_report_is_registered_with_inventory_permission(): void
    {
        $controller = $this->makeReportesController();
        $method = new ReflectionMethod($controller, 'reportDefinitions');

        /** @var array<string, array{label: string, permission: string}> $definitions */
        $definitions = $method->invoke($controller);

        self::assertArrayHasKey('activos_no_verificados', $definitions);
        self::assertSame('reportes.inventario', $definitions['activos_no_verificados']['permission']);
    }

    public function test_pending_inventory_report_exposes_required_traceability_columns(): void
    {
        $controller = $this->makeReportesController();
        $method = new ReflectionMethod($controller, 'columnsFor');

        /** @var array<string, string> $columns */
        $columns = $method->invoke($controller, 'activos_no_verificados');

        self::assertArrayHasKey('numero_activo', $columns);
        self::assertArrayHasKey('ultima_fecha_inventario', $columns);
        self::assertArrayHasKey('ultimo_estatus_localizacion', $columns);
        self::assertArrayHasKey('dias_desde_ultimo_inventario', $columns);
        self::assertArrayHasKey('motivo_no_verificacion', $columns);
    }

    public function test_qr_label_routes_require_asset_view_permission(): void
    {
        $middleware = (new ReflectionClass(SwafiAuth::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($middleware, 'requiredPermissionFor');
        $request = Request::create('/');

        self::assertSame('expedientes.ver', $method->invoke($middleware, $request, 'activos.etiqueta'));
        self::assertSame('expedientes.ver', $method->invoke($middleware, $request, 'activos.etiqueta.auditar'));
    }

    public function test_qr_download_filename_is_sanitized(): void
    {
        $controller = new EtiquetaActivoController();
        $method = new ReflectionMethod($controller, 'safeFileName');

        self::assertSame('BIM_000049', $method->invoke($controller, ' BIM/000049 '));
        self::assertSame('activo', $method->invoke($controller, '///'));
    }

    private function makeReportesController(): ReportesController
    {
        return new ReportesController(new AssetStatusCatalogService());
    }
}
