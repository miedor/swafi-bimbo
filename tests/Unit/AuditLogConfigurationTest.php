<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuditLogConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_security_index_request_validates_audit_filters_and_export_formats(): void
    {
        $request = $this->read('app/Http/Requests/SecurityIndexRequest.php');

        foreach ([
            "Rule::in(['csv', 'xlsx', 'pdf'])",
            "Rule::in([10, 25, 50])",
            "Rule::exists('users', 'id')",
            "Rule::exists('bitacora_auditoria', 'id')",
            "'fecha_desde' => ['nullable', 'date_format:Y-m-d']",
            "'fecha_hasta' => ['nullable', 'date_format:Y-m-d']",
            'La fecha final debe ser igual o posterior a la fecha inicial.',
            "permissions->contains('bitacora.ver')",
        ] as $expected) {
            self::assertStringContainsString($expected, $request);
        }
    }

    public function test_audit_service_uses_parameterized_query_builder_filters_and_pagination_compatible_ordering(): void
    {
        $service = $this->read('app/Services/AuditLogService.php');

        foreach ([
            "DB::table('bitacora_auditoria as b')",
            "->where('b.user_id', (int) \$filters['usuario_bitacora_id'])",
            "->where('b.modulo', \$module)",
            "->where('b.accion', \$action)",
            "->whereDate('b.fecha_evento', '>=',",
            "->whereDate('b.fecha_evento', '<=',",
            "->orderByDesc('b.fecha_evento')",
            "->orderByDesc('b.id')",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringNotContainsString('DB::raw($filters', $service);
    }

    public function test_audit_detail_excludes_sensitive_fields_and_handles_invalid_json(): void
    {
        $service = $this->read('app/Services/AuditLogService.php');

        foreach ([
            'SENSITIVE_KEY_FRAGMENTS',
            "'password'",
            "'remember_token'",
            "'token'",
            "'secret'",
            'Contenido histórico no interpretable.',
            'flattenSnapshot',
            'comparableValue',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_audit_exports_have_a_configurable_limit_and_do_not_load_unbounded_rows(): void
    {
        $service = $this->read('app/Services/AuditLogService.php');
        $config = $this->read('config/swafi.php');
        $environment = $this->read('.env.example');

        self::assertStringContainsString("->limit(\$limit)->get()", $service);
        self::assertStringContainsString('supera el límite de', $service);
        self::assertStringContainsString("config('swafi.bitacora.limite_exportacion', 10000)", $service);
        self::assertStringContainsString("env('SWAFI_AUDIT_EXPORT_LIMIT', 10000)", $config);
        self::assertStringContainsString('SWAFI_AUDIT_EXPORT_LIMIT=10000', $environment);
    }

    public function test_controller_exports_csv_excel_and_pdf_with_controlled_errors(): void
    {
        $controller = $this->read('app/Http/Controllers/SeguridadController.php');

        foreach ([
            "in_array(\$exportFormat, ['csv', 'xlsx', 'pdf'], true)",
            "exportBytes('Bitácora SWAFI'",
            "title: 'Bitácora de auditoría SWAFI'",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            "'Content-Type' => 'application/pdf'",
            'catch (DomainException $exception)',
            'catch (Throwable $exception)',
            'report($exception);',
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }
    }

    public function test_csv_export_prevents_spreadsheet_formula_injection(): void
    {
        $controller = $this->read('app/Http/Controllers/SeguridadController.php');

        self::assertStringContainsString('csvSafeValue', $controller);
        self::assertStringContainsString("['=', '+', '-', '@']", $controller);
        self::assertStringContainsString('return "\'" . $value;', $controller);
    }

    public function test_audit_exports_are_recorded_without_blocking_the_download(): void
    {
        $service = $this->read('app/Services/AuditLogService.php');

        foreach ([
            "'AUDITORIA_EXPORTA_CSV'",
            "'AUDITORIA_EXPORTA_XLSX'",
            "'AUDITORIA_EXPORTA_PDF'",
            "'tabla_afectada' => 'bitacora_auditoria'",
            'catch (Throwable $exception)',
            'report($exception);',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_incremental_migration_adds_indexes_for_user_and_date_filters(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_18_000480_add_query_indexes_to_audit_log.php'
        );

        foreach ([
            "Schema::table('bitacora_auditoria'",
            "['user_id', 'fecha_evento']",
            "['fecha_evento', 'id']",
            'bitacora_user_fecha_index',
            'bitacora_fecha_id_index',
            'public function down(): void',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_interface_exposes_catalog_filters_detail_and_all_export_formats(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');

        foreach ([
            'name="usuario_bitacora_id"',
            'Todas las personas usuarias',
            'Todos los módulos',
            'Todas las acciones',
            'value="xlsx"',
            'Exportar Excel',
            'value="pdf"',
            'Exportar PDF',
            'Ver detalle',
            'Cambios identificados',
            'Valores anteriores y posteriores protegidos contra exposición de secretos.',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
    }

    public function test_interface_hides_unauthorized_security_links_and_remains_responsive(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');
        $controller = $this->read('app/Http/Controllers/SeguridadController.php');

        self::assertStringContainsString('@if ($canManageSecurity)', $view);
        self::assertStringContainsString('@if ($canViewAudit)', $view);
        self::assertStringContainsString('aria-label="Secciones de Seguridad y acceso"', $view);
        self::assertStringContainsString('.sec-audit-meta-grid', $view);
        self::assertStringContainsString('@media (max-width: 760px)', $view);
        self::assertStringContainsString("sessionPermissions->contains('seguridad.administrar')", $controller);
        self::assertStringContainsString("sessionPermissions->contains('bitacora.ver')", $controller);
    }

    public function test_blade_escapes_audit_content_instead_of_rendering_raw_html(): void
    {
        $view = $this->read('resources/views/swafi/seguridad.blade.php');

        self::assertStringContainsString("{{ \$change['before'] }}", $view);
        self::assertStringContainsString("{{ \$change['after'] }}", $view);
        self::assertStringContainsString("{{ \$item['value'] }}", $view);
        self::assertStringNotContainsString("{!! \$change['before'] !!}", $view);
        self::assertStringNotContainsString("{!! \$change['after'] !!}", $view);
    }

    public function test_existing_session_captcha_role_and_export_controls_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $roleService = $this->read('app/Services/RolePermissionManagementService.php');
        $xlsx = $this->read('app/Services/SimpleXlsxExporter.php');
        $pdf = $this->read('app/Services/SimplePdfTableExporter.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('Los roles base del sistema deben permanecer activos.', $roleService);
        self::assertStringContainsString('public function exportBytes', $xlsx);
        self::assertStringContainsString('public function export(string $title', $pdf);
    }

    public function test_new_audit_actions_fit_the_existing_database_column(): void
    {
        foreach ([
            'AUDITORIA_EXPORTA_CSV',
            'AUDITORIA_EXPORTA_XLSX',
            'AUDITORIA_EXPORTA_PDF',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action . ' supera VARCHAR(40).');
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}
