<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ObservationFollowUpConfigurationTest extends TestCase
{
    public function test_incremental_migration_adds_deadline_and_safe_reminder_metadata(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_20_000570_add_observation_deadlines_and_reminders.php'
        );

        foreach ([
            'fecha_compromiso',
            'ultimo_intento_recordatorio_at',
            'fecha_ultimo_recordatorio',
            'recordatorios_enviados',
            'recordatorio_error_referencia',
        ] as $column) {
            self::assertStringContainsString("'{$column}'", $migration);
        }

        self::assertStringContainsString("private const INDEX_NAME = 'idx_obs_followup_due';", $migration);
        self::assertStringContainsString("private const AUDIT_ACTION = 'HABILITA_PLAZOS_OBSERVACIONES';", $migration);
        self::assertStringContainsString("'registro_clave' => 'HU-014'", $migration);
        self::assertStringContainsString("public function down(): void", $migration);
        self::assertStringContainsString("Schema::getIndexes('expediente_observaciones')", $migration);
    }

    public function test_form_request_requires_a_future_deadline_and_active_assignee(): void
    {
        $request = $this->read('app/Http/Requests/StoreExpedienteObservacionRequest.php');

        self::assertStringContainsString("'fecha_compromiso' => [", $request);
        self::assertStringContainsString("'date_format:Y-m-d'", $request);
        self::assertStringContainsString("'after:today'", $request);
        self::assertStringContainsString("Rule::exists('users', 'id')", $request);
        self::assertStringContainsString("->where('estatus', 'activo')", $request);
        self::assertStringContainsString(
            'La fecha compromiso debe ser posterior al día de hoy.',
            $request
        );
    }

    public function test_existing_pending_observations_can_receive_or_change_a_future_deadline(): void
    {
        $request = $this->read('app/Http/Requests/UpdateObservationDeadlineRequest.php');
        $controller = $this->read('app/Http/Controllers/ExpedienteObservacionController.php');
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $view = $this->read('resources/views/swafi/expediente.blade.php');

        self::assertStringContainsString("'nueva_fecha_compromiso' => [", $request);
        self::assertStringContainsString("'after:today'", $request);
        self::assertStringContainsString('UpdateObservationDeadlineRequest $request', $controller);
        self::assertStringContainsString("'OBSERVACION_PLAZO_ACTUALIZADO'", $controller);
        self::assertStringContainsString("'ultimo_intento_recordatorio_at' => null", $controller);
        self::assertStringContainsString("'observaciones.actualizar-fecha'", $routes);
        self::assertStringContainsString(
            "'observaciones.actualizar-fecha' => 'observaciones.validar'",
            $middleware
        );
        self::assertStringContainsString('name="nueva_fecha_compromiso"', $view);
    }

    public function test_controller_persists_deadline_and_includes_it_in_assignment_mail(): void
    {
        $controller = $this->read('app/Http/Controllers/ExpedienteObservacionController.php');
        $mail = $this->read('app/Mail/SwafiObservacionAsignadaMail.php');
        $mailView = $this->read('resources/views/emails/observacion-asignada.blade.php');

        self::assertStringContainsString(
            'StoreExpedienteObservacionRequest $request',
            $controller
        );
        self::assertStringContainsString("'fecha_compromiso' => \$validated['fecha_compromiso']", $controller);
        self::assertStringContainsString('fechaCompromiso:', $controller);
        self::assertStringContainsString('public string $fechaCompromiso', $mail);
        self::assertStringContainsString('{{ $fechaCompromiso }}', $mailView);
    }

    public function test_reminder_service_revalidates_recipient_and_prevents_same_day_duplicates(): void
    {
        $service = $this->read('app/Services/ObservationReminderService.php');

        self::assertStringContainsString(
            'ObservationDeadlineService::REMINDER_STATUSES',
            $service
        );
        self::assertStringContainsString("->whereNull('ultimo_intento_recordatorio_at')", $service);
        self::assertStringContainsString("->orWhere('ultimo_intento_recordatorio_at', '<', \$dayBoundaryUtc)", $service);
        self::assertStringContainsString("->lockForUpdate()", $service);
        self::assertStringContainsString("->where('p.clave', 'observaciones.atender')", $service);
        self::assertStringContainsString("->where('u.estatus', 'activo')", $service);
        self::assertStringContainsString('Mail::to($recipient->email)->send', $service);
        self::assertStringContainsString("'observation_reminder_send'", $service);
        self::assertStringContainsString("'recordatorio_error_referencia' => \$reference", $service);
        self::assertStringNotContainsString('$exception->getMessage()', $service);
    }

    public function test_scheduler_and_environment_configuration_are_present(): void
    {
        $console = $this->read('routes/console.php');
        $config = $this->read('config/swafi.php');
        $env = $this->read('.env.example');
        $command = $this->read('app/Console/Commands/DispatchObservationRemindersCommand.php');

        self::assertStringContainsString('swafi:dispatch-observation-reminders', $console);
        self::assertStringContainsString('->dailyAt(', $console);
        self::assertStringContainsString('->withoutOverlapping(30)', $console);
        self::assertStringContainsString('->onOneServer()', $console);
        self::assertStringContainsString("'observaciones_recordatorios' => [", $config);

        foreach ([
            'SWAFI_OBSERVATION_REMINDERS_ENABLED',
            'SWAFI_OBSERVATION_REMINDERS_TIMEZONE',
            'SWAFI_OBSERVATION_REMINDERS_TIME',
            'SWAFI_OBSERVATION_DUE_SOON_DAYS',
            'SWAFI_OBSERVATION_REMINDERS_BATCH_LIMIT',
        ] as $key) {
            self::assertStringContainsString($key, $env);
        }

        self::assertStringContainsString('ObservationReminderService $reminders', $command);
        self::assertStringContainsString("\$summary['fallidas'] > 0", $command);
    }

    public function test_detail_view_preserves_values_and_displays_deadline_status(): void
    {
        $view = $this->read('resources/views/swafi/expediente.blade.php');
        $controller = $this->read('app/Http/Controllers/BusquedaController.php');

        self::assertStringContainsString('name="fecha_compromiso"', $view);
        self::assertStringContainsString("value=\"{{ old('fecha_compromiso') }}\"", $view);
        self::assertStringContainsString('ObservationDeadlineService::class', $view);
        self::assertStringContainsString("observaciones_vencidas", $view);
        self::assertStringContainsString("recordatorio_error_referencia", $view);
        self::assertStringContainsString("'observaciones_vencidas' =>", $controller);
        self::assertStringContainsString("'observaciones_por_vencer' =>", $controller);
        self::assertStringContainsString("NULL as fecha_compromiso", $controller);
    }

    public function test_audit_actions_fit_the_existing_varchar_40_column(): void
    {
        foreach ([
            'HABILITA_PLAZOS_OBSERVACIONES',
            'RECORDATORIO_OBS_ENVIADO',
            'RECORDATORIO_OBS_FALLIDO',
            'OBSERVACION_PLAZO_ACTUALIZADO',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action . ' supera VARCHAR(40).');
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . ltrim($path, '/'));

        self::assertIsString($contents, "No fue posible leer {$path}.");

        return $contents;
    }
}
