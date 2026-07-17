<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValorActivoHistoryConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_history_route_is_protected_and_uses_a_constrained_asset_identifier(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString('ValorActivoHistoryController', $routes);
        self::assertStringContainsString("->name('valores.historial')", $routes);
        self::assertStringContainsString("->where('numeroActivo', '[A-Za-z0-9._-]+')", $routes);
        self::assertStringContainsString(
            "'valores.historial' => 'valores.ver'",
            $middleware
        );
    }

    public function test_filter_request_validates_permission_dates_user_and_page_size(): void
    {
        $request = $this->read('app/Http/Requests/FilterValorActivoHistoryRequest.php');

        foreach ([
            "canCurrentUser('valores.ver')",
            "'usuario_id'",
            "'exists:users,id'",
            "'date_format:Y-m-d'",
            "'after_or_equal:fecha_desde'",
            'Rule::in([10, 25, 50])',
            "'regex:/^[A-Z0-9_]+$/'",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_service_uses_paginated_joined_queries_without_n_plus_one(): void
    {
        $service = $this->read('app/Services/ValorActivoHistoryService.php');

        foreach ([
            "DB::table('bitacora_auditoria as b')",
            "->leftJoin('users as u'",
            "->where('b.tabla_afectada', 'valores_activo')",
            '->paginate($perPage',
            "->whereDate('b.fecha_evento'",
            'buildChanges',
            'JSON_THROW_ON_ERROR',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringNotContainsString('DB::raw($', $service);
    }

    public function test_controller_registers_the_sensitive_query_without_polluting_value_history(): void
    {
        $controller = $this->read('app/Http/Controllers/ValorActivoHistoryController.php');

        self::assertStringContainsString("'accion' => 'CONSULTA_HIST_VALORES'", $controller);
        self::assertStringContainsString("'tabla_afectada' => 'bitacora_auditoria'", $controller);
        self::assertStringContainsString('array_intersect_key($filters', $controller);
        self::assertStringContainsString('report($exception);', $controller);
        self::assertLessThanOrEqual(40, strlen('CONSULTA_HIST_VALORES'));
    }

    public function test_interface_preserves_navigation_responsiveness_and_escaped_output(): void
    {
        $view = $this->read('resources/views/swafi/valores-historial.blade.php');
        $values = $this->read('resources/views/swafi/valores.blade.php');
        $layout = $this->read('resources/views/layouts/app.blade.php');

        foreach ([
            'data-swafi-query-panel',
            'data-swafi-query-results',
            'Consultar histórico',
            'Volver a valores',
            'aria-label="Paginación del histórico"',
            '@media (max-width: 720px)',
            "{{ \$change['after'] }}",
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString("{!! \$change['after'] !!}", $view);
        self::assertStringContainsString("route('valores.historial', \$row->numero_activo)", $values);
        self::assertStringContainsString("request()->routeIs('valores*')", $layout);
    }

    public function test_existing_session_query_export_and_logical_deletion_controls_remain_present(): void
    {
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $queryUx = $this->read('public/assets/swafi/js/swafi-query-results.js');
        $values = $this->read('app/Http/Controllers/ValoresActivoController.php');
        $model = $this->read('app/Models/ValorActivo.php');

        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("const FOCUS_PARAMETER = 'swafi_focus';", $queryUx);
        self::assertStringContainsString('exportCsv($query)', $values);
        self::assertStringContainsString('use SoftDeletes;', $model);
        self::assertStringContainsString("'BAJA_LOGICA_VALOR'", $values);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
