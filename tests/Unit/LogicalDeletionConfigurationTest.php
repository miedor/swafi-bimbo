<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LogicalDeletionConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function test_migration_adds_traceable_logical_deletion_fields_to_business_records(): void
    {
        $migration = $this->read('database/migrations/2026_07_15_000420_add_logical_deletion_to_swafi_records.php');

        foreach ([
            'expedientes',
            'valores_activo',
            'busquedas_guardadas',
            'reportes_guardados',
        ] as $table) {
            self::assertStringContainsString("'{$table}'", $migration);
        }

        self::assertStringContainsString('$table->softDeletes();', $migration);
        self::assertStringContainsString("foreignId('deleted_by')", $migration);
        self::assertStringContainsString("string('delete_reason', 500)", $migration);
    }

    public function test_business_models_use_laravel_soft_deletes(): void
    {
        foreach ([
            'app/Models/Expediente.php',
            'app/Models/ValorActivo.php',
            'app/Models/BusquedaGuardada.php',
            'app/Models/ReporteGuardado.php',
        ] as $path) {
            $model = $this->read($path);

            self::assertStringContainsString('use Illuminate\\Database\\Eloquent\\SoftDeletes;', $model, $path);
            self::assertMatchesRegularExpression('/use\s+(?:HasFactory,\s*)?SoftDeletes;/', $model, $path);
            self::assertStringContainsString("'deleted_by'", $model, $path);
            self::assertStringContainsString("'delete_reason'", $model, $path);
        }
    }

    public function test_deletion_actions_keep_the_record_and_write_audit_metadata(): void
    {
        $expedientes = $this->read('app/Http/Controllers/ExpedienteGestionController.php');
        $valores = $this->read('app/Http/Controllers/ValoresActivoController.php');
        $busquedas = $this->read('app/Http/Controllers/BusquedaGuardadaController.php');
        $reportes = $this->read('app/Http/Controllers/ReporteGuardadoController.php');

        self::assertStringContainsString("'EXPEDIENTE_BAJA_LOGICA'", $expedientes);
        self::assertStringContainsString("'deleted_at' => now()", $expedientes);
        self::assertStringContainsString("'deleted_by' => auth()->id()", $expedientes);
        self::assertStringContainsString("'delete_reason' => \$motivoBaja", $expedientes);
        self::assertStringNotContainsString("DB::table('expedientes')\n                ->where('id', $detalle->expediente_id)\n                ->delete()", $expedientes);

        self::assertStringContainsString("'BAJA_LOGICA_VALOR'", $valores);
        self::assertStringContainsString('$record->delete();', $valores);
        self::assertStringContainsString('use SoftDeletes;', $this->read('app/Models/ValorActivo.php'));

        self::assertStringContainsString("'BUSQUEDA_GUARDADA_BAJA_LOGICA'", $busquedas);
        self::assertStringContainsString('$busquedaData->delete();', $busquedas);

        self::assertStringContainsString("'REPORTE_GUARDADO_BAJA_LOGICA'", $reportes);
        self::assertStringContainsString('$savedReport->delete();', $reportes);
    }

    public function test_operational_queries_exclude_archived_records(): void
    {
        $expectedGuards = [
            'app/Http/Controllers/BusquedaController.php' => "whereNull('e.deleted_at')",
            'app/Http/Controllers/DashboardController.php' => "whereNull('e.deleted_at')",
            'app/Http/Controllers/ReportesController.php' => "whereNull('e.deleted_at')",
            'app/Http/Controllers/ValoresActivoController.php' => "whereNull('v.deleted_at')",
            'app/Http/Controllers/RegistroMasivoController.php' => "whereNull('e.deleted_at')",
            'app/Services/CfdiValidationService.php' => "whereNull('deleted_at')",
        ];

        foreach ($expectedGuards as $path => $guard) {
            self::assertStringContainsString($guard, $this->read($path), $path);
        }
    }

    public function test_user_interface_describes_the_operation_as_logical_deletion(): void
    {
        foreach ([
            'resources/views/swafi/busqueda.blade.php',
            'resources/views/swafi/valores.blade.php',
            'resources/views/swafi/reportes.blade.php',
        ] as $path) {
            $view = $this->read($path);

            self::assertStringContainsString('name="motivo_baja"', $view, $path);
            self::assertStringContainsString('Dar de baja', $view, $path);
            self::assertStringContainsString('lógicamente', $view, $path);
        }
    }

    public function test_banking_style_session_regressions_remain_covered(): void
    {
        $script = $this->read('public/assets/swafi/js/swafi-session.js');
        $routes = $this->read('routes/web.php');
        $configuration = $this->read('config/session.php');

        self::assertStringContainsString("addEventListener('popstate'", $script);
        self::assertStringContainsString("navigationEntry.type === 'back_forward'", $script);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $script);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $script);
        self::assertStringContainsString("Route::post('/logout'", $routes);
        self::assertStringContainsString("env('SESSION_EXPIRE_ON_CLOSE', true)", $configuration);
        self::assertStringContainsString("env('SESSION_LIFETIME', 10)", $configuration);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->projectRoot.'/'.$relativePath);

        self::assertIsString($contents, "No fue posible leer {$relativePath}.");

        return $contents;
    }
}
