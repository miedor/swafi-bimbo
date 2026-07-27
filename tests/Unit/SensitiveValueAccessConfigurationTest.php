<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SensitiveValueAccessConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_full_financial_access_requires_management_or_financial_report_permission(): void
    {
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');

        self::assertStringContainsString('private function canViewSensitiveValues(): bool', $controller);
        self::assertStringContainsString("canCurrentUser('valores.administrar')", $controller);
        self::assertStringContainsString("canCurrentUser('reportes.valores')", $controller);
        self::assertStringContainsString('$this->baseQuery($canViewSensitiveValues)', $controller);
        self::assertStringContainsString('$this->applyFilters($query, $request, $canViewSensitiveValues)', $controller);
        self::assertStringContainsString('if (!$includeSensitiveValues) {', $controller);
        self::assertStringContainsString('return $query->select($commonColumns);', $controller);
    }

    public function test_basic_users_export_only_the_operational_projection_visible_to_their_profile(): void
    {
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');
        $request = $this->read('app/Http/Requests/FilterValoresActivoRequest.php');

        self::assertStringContainsString("'proveedor_id' => 'a.proveedor_id'", $controller);
        self::assertStringContainsString("'moneda' => 'v.moneda'", $controller);
        self::assertMatchesRegularExpression(
            '/if \(!\$includeSensitiveValues\) \{\s+return;/s',
            $controller
        );
        self::assertStringContainsString("canCurrentUser('valores.ver')", $controller);
        self::assertStringContainsString('private function exportColumns(bool $includeSensitiveValues): array', $controller);
        self::assertStringContainsString("'estatus_operativo' => 'Estatus operativo'", $controller);
        self::assertStringContainsString("'valor_fiscal' => 'Valor fiscal'", $controller);
        self::assertStringContainsString("\$scope = \$includeSensitiveValues ? 'completo' : 'operativo_basico';", $controller);
        self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $request);
        self::assertStringContainsString("'canExportarExcel' => \$canViewSensitiveValues", $controller);
        self::assertStringContainsString("'canExportarPdf' => \$canViewSensitiveValues", $controller);
    }

    public function test_history_and_individual_exports_are_denied_before_loading_sensitive_data(): void
    {
        $historyRequest = $this->read('app/Http/Requests/FilterValorActivoHistoryRequest.php');
        $exportRequest = $this->read('app/Http/Requests/ExportValorActivoFichaRequest.php');

        foreach ([$historyRequest, $exportRequest] as $request) {
            self::assertStringContainsString("canCurrentUser('valores.administrar')", $request);
            self::assertStringContainsString("canCurrentUser('reportes.valores')", $request);
            self::assertStringNotContainsString("canCurrentUser('valores.ver')", $request);
        }
    }

    public function test_plant_role_keeps_basic_read_access_without_financial_report_permission(): void
    {
        $migration = $this->read('database/migrations/2026_07_13_000400_sync_swafi_role_permissions.php');
        $plantStart = strpos($migration, "'Usuario Planta / Inventarios' => [");
        $plantEnd = strpos($migration, '        ];', $plantStart ?: 0);

        self::assertNotFalse($plantStart);
        self::assertNotFalse($plantEnd);

        $plantBlock = substr($migration, (int) $plantStart, (int) $plantEnd - (int) $plantStart);

        self::assertStringContainsString("'valores.ver'", $plantBlock);
        self::assertStringNotContainsString("'reportes.valores'", $plantBlock);
    }

    public function test_view_renders_a_basic_operational_projection_without_sensitive_columns(): void
    {
        $view = $this->read('resources/views/swafi/valores.blade.php');

        self::assertStringContainsString('Tu perfil cuenta con consulta operativa básica.', $view);
        self::assertStringContainsString('SWAFI oculta montos, proveedor, factura, moneda, tipo de cambio e historial financiero', $view);
        self::assertStringContainsString('Las exportaciones incluyen únicamente las columnas operativas visibles para tu perfil.', $view);
        self::assertStringContainsString('Exportar Excel', $view);
        self::assertStringContainsString('Exportar PDF', $view);
        self::assertStringContainsString('@if($canViewSensitiveValues)', $view);
        self::assertStringContainsString('<th>Ubicación / clasificación</th>', $view);
        self::assertStringContainsString('<th>Soporte XML</th>', $view);
        self::assertStringContainsString('Consultar expediente', $view);
        self::assertStringContainsString('{{ $canViewSensitiveValues ? 8 : 6 }}', $view);
    }

    public function test_history_audit_failures_use_the_safe_exception_reporter(): void
    {
        $controller = $this->read('app/Http/Controllers/ValorActivoHistoryController.php');

        self::assertStringContainsString('SafeExceptionReporter', $controller);
        self::assertStringContainsString('asset_value_history_query_audit', $controller);
        self::assertStringNotContainsString('report($exception);', $controller);
        self::assertStringNotContainsString('getMessage()', $controller);
    }

    public function test_bulk_import_does_not_expose_internal_exception_messages(): void
    {
        $controller = $this->read('app/Http/Controllers/ValoresActivoController.php');
        $technicalCatch = $this->catchBody($controller, '\\Throwable $exception');

        self::assertStringContainsString('SafeExceptionReporter', $controller);
        self::assertStringContainsString('$this->safeExceptions->warning(', $technicalCatch);
        self::assertStringContainsString("'asset_values_bulk_import'", $technicalCatch);
        self::assertStringNotContainsString('report($exception);', $technicalCatch);
        self::assertStringNotContainsString('$exception->getMessage()', $technicalCatch);
        self::assertStringContainsString(
            'La importación fue revertida. Referencia: {$reference}.',
            $technicalCatch
        );
    }

    private function catchBody(string $contents, string $signature): string
    {
        $needle = 'catch (' . $signature . ') {';
        $start = strpos($contents, $needle);

        self::assertNotFalse($start, "No se encontró el bloque {$needle}");

        $openingBrace = (int) $start + strlen($needle) - 1;
        $depth = 1;
        $cursor = $openingBrace + 1;
        $length = strlen($contents);

        while ($cursor < $length && $depth > 0) {
            if ($contents[$cursor] === '{') {
                $depth++;
            } elseif ($contents[$cursor] === '}') {
                $depth--;
            }

            $cursor++;
        }

        self::assertSame(0, $depth, "El bloque {$needle} no tiene llaves balanceadas.");

        return substr($contents, $openingBrace + 1, max(0, $cursor - $openingBrace - 2));
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, $relativePath);

        return $contents;
    }
}
