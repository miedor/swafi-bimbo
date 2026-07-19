<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ScheduledReportConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_migration_creates_traceable_scheduled_report_tables_and_permission(): void
    {
        $migration = $this->read('database/migrations/2026_07_19_000540_create_scheduled_reports.php');

        self::assertStringContainsString("Schema::create('reportes_programados'", $migration);
        self::assertStringContainsString("Schema::create('reportes_programados_ejecuciones'", $migration);
        self::assertStringContainsString("softDeletes()", $migration);
        self::assertStringContainsString("restrictOnDelete()", $migration);
        self::assertStringContainsString("'reportes.programar'", $migration);
        self::assertStringContainsString("'Usuario Consulta / Auditoría'", $migration);
        self::assertStringContainsString('reportes_programados_ejecucion_unique', $migration);
        self::assertStringContainsString('HABILITA_REPORTES_PROGRAMADOS', $migration);
    }

    public function test_server_validation_restricts_frequency_recipients_format_and_ownership(): void
    {
        $request = $this->read('app/Http/Requests/StoreScheduledReportRequest.php');

        self::assertStringContainsString("Rule::exists('reportes_guardados', 'id')", $request);
        self::assertStringContainsString("->where('user_id', \$userId)", $request);
        self::assertStringContainsString("Rule::in(['diaria', 'semanal', 'mensual'])", $request);
        self::assertStringContainsString("'required_if:frecuencia,semanal'", $request);
        self::assertStringContainsString("'required_if:frecuencia,mensual'", $request);
        self::assertStringContainsString("Rule::in(['csv', 'xlsx', 'pdf'])", $request);
        self::assertStringContainsString("'email:rfc'", $request);
        self::assertStringContainsString("'max:10'", $request);
        self::assertStringContainsString('dominios_destinatarios_permitidos', $request);
        self::assertStringContainsString('dominio no autorizado', $request);
    }

    public function test_routes_use_authentication_and_the_specific_schedule_permission(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        foreach ([
            'reportes-programados.store',
            'reportes-programados.toggle',
            'reportes-programados.destroy',
        ] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
            self::assertStringContainsString("'{$routeName}'", $middleware);
        }

        self::assertStringContainsString("=> 'reportes.programar'", $middleware);
    }

    public function test_scheduler_and_queue_are_configurable_for_laravel_cloud(): void
    {
        $console = $this->read('routes/console.php');
        $config = $this->read('config/swafi.php');
        $environment = $this->read('.env.example');
        $command = $this->read('app/Console/Commands/DispatchScheduledReportsCommand.php');

        self::assertStringContainsString('swafi:dispatch-scheduled-reports', $console);
        self::assertStringContainsString('everyFiveMinutes()', $console);
        self::assertStringContainsString('withoutOverlapping(10)', $console);
        self::assertStringContainsString('onOneServer()', $console);
        self::assertStringContainsString('SWAFI_SCHEDULED_REPORTS_ENABLED', $environment);
        self::assertStringContainsString('SWAFI_SCHEDULED_REPORTS_QUEUE', $environment);
        self::assertStringContainsString('SWAFI_SCHEDULED_REPORTS_ALLOWED_DOMAINS', $environment);
        self::assertStringContainsString("'cola' => env('SWAFI_SCHEDULED_REPORTS_QUEUE'", $config);
        self::assertStringContainsString('dispatchDue(', $command);
    }

    public function test_job_prevents_duplicate_executions_and_duplicate_recipient_delivery_on_retry(): void
    {
        $service = $this->read('app/Services/ScheduledReportService.php');
        $job = $this->read('app/Jobs/GenerateScheduledReportJob.php');

        self::assertStringContainsString('firstOrCreate(', $service);
        self::assertStringContainsString('wasRecentlyCreated', $service);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString('destinatarios_enviados', $job);
        self::assertStringContainsString("in_array(\$recipient, \$sentRecipients, true)", $job);
        self::assertStringContainsString('public int $tries = 3', $job);
        self::assertStringContainsString('public array $backoff', $job);
        self::assertStringContainsString('dominios_destinatarios_permitidos', $job);
        self::assertStringContainsString('ya no están autorizados', $job);
    }

    public function test_generation_reuses_current_report_filters_columns_and_exporters(): void
    {
        $controller = $this->read('app/Http/Controllers/ReportesController.php');

        self::assertStringContainsString('generateScheduledExport(', $controller);
        self::assertStringContainsString('$this->queryForReport(', $controller);
        self::assertStringContainsString('$this->applyFilters(', $controller);
        self::assertStringContainsString('$this->applyOrder(', $controller);
        self::assertStringContainsString('$this->selectedColumns(', $controller);
        self::assertStringContainsString('$xlsxExporter->exportBytes(', $controller);
        self::assertStringContainsString('$pdfExporter->export(', $controller);
        self::assertStringContainsString('self::EXPORT_LIMIT + 1', $controller);
    }

    public function test_saved_report_logical_deletion_also_stops_its_schedule(): void
    {
        $controller = $this->read('app/Http/Controllers/ReporteGuardadoController.php');

        self::assertStringContainsString('ReporteProgramado::query()', $controller);
        self::assertStringContainsString("'activo' => false", $controller);
        self::assertStringContainsString("'proxima_ejecucion_at' => null", $controller);
        self::assertStringContainsString("'ultimo_estado' => 'eliminado'", $controller);
        self::assertStringContainsString('$programacion->delete()', $controller);
        self::assertStringContainsString('DB::transaction(', $controller);
    }

    public function test_interface_preserves_saved_reports_and_adds_inline_scheduling(): void
    {
        $view = $this->read('resources/views/swafi/reportes.blade.php');

        foreach ([
            'Guardar plantilla',
            'Mis reportes guardados',
            'Aplicar',
            'Dar de baja',
            'Programar envío periódico',
            'Actualizar programación',
            'Suspender',
            'Reactivar',
            'data-scheduled-report-form',
        ] as $feature) {
            self::assertStringContainsString($feature, $view);
        }
    }

    public function test_failures_use_safe_references_without_storing_exception_messages(): void
    {
        $job = $this->read('app/Jobs/GenerateScheduledReportJob.php');

        self::assertStringContainsString('exceptionReference(', $job);
        self::assertStringContainsString('SafeExceptionReporter', $job);
        self::assertStringContainsString("'error_referencia'", $job);
        self::assertStringNotContainsString('getMessage()', $job);
        self::assertStringContainsString('REPORTE_PROGRAMADO_FALLIDO', $job);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, $relativePath);

        return $contents;
    }
}
