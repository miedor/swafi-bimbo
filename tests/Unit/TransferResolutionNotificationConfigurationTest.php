<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TransferResolutionNotificationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_migration_adds_requester_notification_traceability_and_queue_indexes(): void
    {
        $migration = $this->read('database/migrations/2026_07_26_000690_add_transfer_resolution_notifications.php');

        foreach ([
            'notificacion_solicitante_at',
            'ultimo_intento_notificacion_solicitante_at',
            'notificacion_solicitante_intentos',
            'notificacion_solicitante_error',
            'idx_traslado_aprobador_estatus',
            'idx_traslado_solicitante_estatus',
        ] as $token) {
            self::assertStringContainsString($token, $migration);
        }

        self::assertStringContainsString('HABILITA_NOTIF_RESOL_TRASLADO', $migration);
    }

    public function test_defined_role_matrix_separates_plant_request_from_capture_approval(): void
    {
        $baseRoles = $this->read('database/migrations/2026_07_13_000400_sync_swafi_role_permissions.php');
        $transferPermissions = $this->read('database/migrations/2026_07_16_000440_create_transfer_approval_and_inventory_lock_tables.php');

        self::assertStringContainsString("'Usuario Planta / Inventarios'", $baseRoles);
        self::assertStringContainsString("'ubicaciones.administrar'", $baseRoles);
        self::assertStringContainsString("'Usuario Captura'", $transferPermissions);
        self::assertStringContainsString("'ubicaciones.aprobar_traslados'", $transferPermissions);
        self::assertStringContainsString("'Usuario Planta / Inventarios' => ['ubicaciones.ver']", $transferPermissions);
    }

    public function test_cross_plant_request_preserves_location_and_validates_requester_role_and_email(): void
    {
        $workflow = $this->read('app/Services/TransferWorkflowService.php');

        self::assertStringContainsString('resolveTransferRequester', $workflow);
        self::assertStringContainsString("in_array('ubicaciones.administrar', \$context['permissions'], true)", $workflow);
        self::assertStringContainsString('FILTER_VALIDATE_EMAIL', $workflow);
        self::assertStringContainsString("'estatus' => 'pendiente'", $workflow);
        self::assertStringContainsString("'solicitado_por' => (int) \$requester->id", $workflow);
        self::assertStringContainsString("'notificacion_solicitante_intentos' => 0", $workflow);
        self::assertStringContainsString('La ubicación actual no fue modificada.', $workflow);
    }

    public function test_approval_and_rejection_notify_the_requester_without_rolling_back_resolution(): void
    {
        $controller = $this->read('app/Http/Controllers/TransferApprovalController.php');
        $service = $this->read('app/Services/TransferNotificationService.php');

        self::assertGreaterThanOrEqual(2, substr_count($controller, 'sendResolution'));
        self::assertStringContainsString('public function sendResolution', $service);
        self::assertStringContainsString('SwafiResolucionTrasladoMail', $service);
        self::assertStringContainsString('NOTIF_RESOL_TRASLADO_ENVIADA', $service);
        self::assertStringContainsString('NOTIF_RESOL_TRASLADO_FALLIDA', $service);
        self::assertStringContainsString("'sent' => false", $service);
        self::assertStringContainsString('La solicitud quedó', $service);
    }

    public function test_resolution_mail_contains_status_operational_detail_and_direct_link(): void
    {
        $mail = $this->read('app/Mail/SwafiResolucionTrasladoMail.php');
        $view = $this->read('resources/views/emails/resolucion-traslado.blade.php');

        self::assertStringContainsString("->view('emails.resolucion-traslado')", $mail);
        self::assertStringContainsString("->subject('SWAFI | Traslado '", $mail);
        self::assertStringContainsString('Ubicación de origen', $view);
        self::assertStringContainsString('Ubicación de destino', $view);
        self::assertStringContainsString('Resolución', $view);
        self::assertStringContainsString('Consultar solicitud en SWAFI', $view);
    }

    public function test_retry_routes_are_relation_authorized_and_require_location_view_permission(): void
    {
        $controller = $this->read('app/Http/Controllers/TransferApprovalController.php');
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString('resendResolutionNotification', $controller);
        self::assertStringContainsString('assertCanRetryResolutionNotification', $controller);
        self::assertStringContainsString('ubicacion.traslados.notificar-resolucion', $routes);
        self::assertStringContainsString("'ubicacion.traslados.notificar-resolucion' => 'ubicaciones.ver'", $middleware);
    }

    public function test_dashboard_exposes_capture_approval_queue_and_requester_follow_up_queue(): void
    {
        $queue = $this->read('app/Services/TransferDashboardQueueService.php');
        $controller = $this->read('app/Http/Controllers/DashboardController.php');
        $view = $this->read('resources/views/swafi/dashboard.blade.php');

        self::assertStringContainsString('pendingForApprover', $queue);
        self::assertStringContainsString('latestForRequester', $queue);
        self::assertStringContainsString("->where('st.aprobador_asignado_id', \$userId)", $queue);
        self::assertStringContainsString("->where('st.solicitado_por', \$userId)", $queue);
        self::assertStringContainsString('traslados_pendientes_aprobacion', $controller);
        self::assertStringContainsString('traslados_propios_pendientes', $controller);
        self::assertStringContainsString('data-dashboard-tab="transferencias-aprobar"', $view);
        self::assertStringContainsString('data-dashboard-tab="mis-transferencias"', $view);
        self::assertStringContainsString('Traslados entre plantas asignados para aprobación', $view);
        self::assertStringContainsString('Mis solicitudes de cambio de ubicación entre plantas', $view);
    }

    public function test_m02_interface_shows_both_email_states_and_manual_retries(): void
    {
        $view = $this->read('resources/views/swafi/ubicacion.blade.php');
        $partial = $this->read('resources/views/swafi/partials/transfer-approvals.blade.php');

        self::assertStringContainsString('recibirá por correo el resultado de la resolución', $view);
        self::assertStringContainsString('Aviso al aprobador', $partial);
        self::assertStringContainsString('Aviso al solicitante', $partial);
        self::assertStringContainsString('Reenviar asignación', $partial);
        self::assertStringContainsString('Reenviar resultado', $partial);
        self::assertStringContainsString('ubicacion.traslados.notificar-resolucion', $partial);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
