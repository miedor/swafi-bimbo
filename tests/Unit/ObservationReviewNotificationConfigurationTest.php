<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ObservationReviewNotificationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_migration_adds_review_resolution_traceability_and_dashboard_index(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_22_000630_add_observation_review_notifications.php'
        );

        foreach ([
            'fecha_notificacion_revision',
            'ultimo_intento_notificacion_revision_at',
            'notificacion_revision_intentos',
            'notificacion_revision_error_referencia',
            'fecha_notificacion_resolucion',
            'ultimo_intento_notificacion_resolucion_at',
            'notificacion_resolucion_intentos',
            'notificacion_resolucion_error_referencia',
        ] as $column) {
            self::assertStringContainsString("'{$column}'", $migration);
        }

        self::assertStringContainsString("private const VALIDATION_INDEX = 'idx_obs_validation_queue';", $migration);
        self::assertStringContainsString("'HU-014-VALIDACION'", $migration);
        self::assertStringContainsString('public function down(): void', $migration);
        self::assertStringContainsString("Schema::getIndexes('expediente_observaciones')", $migration);
    }

    public function test_attending_and_validating_trigger_the_two_workflow_notifications(): void
    {
        $controller = $this->read('app/Http/Controllers/ExpedienteObservacionController.php');
        $service = $this->read('app/Services/ObservationWorkflowNotificationService.php');

        self::assertStringContainsString('ObservationWorkflowNotificationService $workflowNotifications', $controller);
        self::assertStringContainsString('notifyCreatorForValidation(', $controller);
        self::assertStringContainsString('notifyAssigneeOfResolution(', $controller);
        self::assertStringContainsString("'estatus' => 'atendida'", $controller);
        self::assertStringContainsString("'decision' => ['required', 'in:cerrada,rechazada']", $controller);

        self::assertStringContainsString('public function notifyCreatorForValidation(', $service);
        self::assertStringContainsString('public function notifyAssigneeOfResolution(', $service);
        self::assertStringContainsString("->where('p.clave', \$permission)", $service);
        self::assertStringContainsString("'NOTIF_OBS_REVISION_ENVIADA'", $service);
        self::assertStringContainsString("'NOTIF_OBS_RESOLUCION_ENVIADA'", $service);
    }

    public function test_mail_messages_include_direct_links_and_operational_context(): void
    {
        $attendedMail = $this->read('app/Mail/SwafiObservacionAtendidaMail.php');
        $attendedView = $this->read('resources/views/emails/observacion-atendida.blade.php');
        $resolutionMail = $this->read('app/Mail/SwafiObservacionResolucionMail.php');
        $resolutionView = $this->read('resources/views/emails/observacion-resolucion.blade.php');

        self::assertStringContainsString('Observación atendida pendiente de validación', $attendedMail);
        self::assertStringContainsString("->view('emails.observacion-atendida')", $attendedMail);
        self::assertStringContainsString('Validar observación en SWAFI', $attendedView);
        self::assertStringContainsString('acepta y se cierra o si debe rechazarse', $attendedView);
        self::assertStringContainsString('{{ $respuestaAtencion }}', $attendedView);

        self::assertStringContainsString('Resolución de observación', $resolutionMail);
        self::assertStringContainsString("->view('emails.observacion-resolucion')", $resolutionMail);
        self::assertStringContainsString('Comentario de validación', $resolutionView);
        self::assertStringContainsString('regresa al flujo de atención', $resolutionView);
    }

    public function test_dashboard_exposes_a_personal_validation_queue_without_weakening_permissions(): void
    {
        $controller = $this->read('app/Http/Controllers/DashboardController.php');
        $view = $this->read('resources/views/swafi/dashboard.blade.php');
        $detail = $this->read('resources/views/swafi/expediente.blade.php');

        self::assertStringContainsString('private function observationValidationQueue(): array', $controller);
        self::assertStringContainsString("in_array('observaciones.validar', \$permissions, true)", $controller);
        self::assertStringContainsString("->where('o.estatus', 'atendida')", $controller);
        self::assertStringContainsString("->where('o.creado_por', \$userId)", $controller);
        self::assertStringContainsString('observacionesPendientesValidacion', $controller);

        self::assertStringContainsString('Validaciones pendientes', $view);
        self::assertStringContainsString('Aceptar o rechazar', $view);
        self::assertStringContainsString("\$can('observaciones.validar')", $view);
        self::assertStringContainsString('data-open-validation-queue', $view);
        self::assertStringContainsString('id="observacion-{{ $observacion->id }}"', $detail);
    }

    public function test_notification_failures_use_safe_references_and_do_not_expose_exception_messages(): void
    {
        $service = $this->read('app/Services/ObservationWorkflowNotificationService.php');

        self::assertStringContainsString("'observation_validation_notification_send'", $service);
        self::assertStringContainsString("'observation_resolution_notification_send'", $service);
        self::assertStringContainsString("'notificacion_revision_error_referencia' => \$reference", $service);
        self::assertStringContainsString("'notificacion_resolucion_error_referencia' => \$reference", $service);
        self::assertStringNotContainsString('$exception->getMessage()', $service);
        self::assertStringContainsString('La observación quedó atendida y visible en el Dashboard', $service);
    }

    public function test_new_audit_actions_fit_the_existing_varchar_40_column(): void
    {
        foreach ([
            'HABILITA_AVISO_REVISION_OBS',
            'NOTIF_OBS_REVISION_ENVIADA',
            'NOTIF_OBS_REVISION_FALLIDA',
            'NOTIF_OBS_RESOLUCION_ENVIADA',
            'NOTIF_OBS_RESOLUCION_FALLIDA',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action.' supera VARCHAR(40).');
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root.'/'.ltrim($path, '/'));

        self::assertIsString($contents, "No fue posible leer {$path}.");

        return $contents;
    }
}
