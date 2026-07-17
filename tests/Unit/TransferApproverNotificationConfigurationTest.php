<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TransferApproverNotificationConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_migration_adds_assigned_approver_and_notification_traceability(): void
    {
        $migration = $this->read('database/migrations/2026_07_16_000450_add_assigned_approver_and_notification_to_transfer_requests.php');

        foreach ([
            'aprobador_asignado_id',
            'notificacion_aprobador_at',
            'ultimo_intento_notificacion_at',
            'notificacion_aprobador_intentos',
            'notificacion_aprobador_error',
        ] as $column) {
            self::assertStringContainsString($column, $migration);
        }

        self::assertStringContainsString("constrained('users')", $migration);
        self::assertStringContainsString('HABILITA_APROBADOR_TRASLADO', $migration);
        self::assertStringContainsString("DB::table('bitacora_auditoria')->updateOrInsert", $migration);
    }

    public function test_cross_plant_transfer_requires_an_active_capture_user_and_separation_of_duties(): void
    {
        $request = $this->read('app/Http/Requests/StoreMovimientoUbicacionRequest.php');
        $service = $this->read('app/Services/TransferWorkflowService.php');

        self::assertStringContainsString("'aprobador_asignado_id'", $request);
        self::assertStringContainsString('resolveActiveCaptureApprover', $service);
        self::assertStringContainsString("->where('r.nombre', 'Usuario Captura')", $service);
        self::assertStringContainsString("->where('p.clave', 'ubicaciones.aprobar_traslados')", $service);
        self::assertStringContainsString('la persona solicitante no puede asignarse a sí misma', $service);
        self::assertStringContainsString("'aprobador_asignado_id' => (int) \$assignedApprover->id", $service);
    }

    public function test_only_assigned_capture_user_or_administrator_can_resolve(): void
    {
        $service = $this->read('app/Services/TransferWorkflowService.php');
        $controller = $this->read('app/Http/Controllers/UbicacionInventarioController.php');

        self::assertStringContainsString('assertAssignedApproverCanResolve', $service);
        self::assertStringContainsString("if (\$context['is_admin'])", $service);
        self::assertStringContainsString("(int) (\$request->aprobador_asignado_id ?? 0) !== \$userId", $service);
        self::assertStringContainsString("->where('st.aprobador_asignado_id', \$this->userId())", $controller);
        self::assertStringContainsString("->where('aprobador_asignado_id', \$this->userId())", $controller);
    }

    public function test_mail_notification_contains_operational_detail_and_direct_review_link(): void
    {
        $mail = $this->read('app/Mail/SwafiSolicitudTrasladoMail.php');
        $view = $this->read('resources/views/emails/solicitud-traslado.blade.php');
        $service = $this->read('app/Services/TransferNotificationService.php');

        self::assertStringContainsString('Traslado pendiente de aprobación', $mail);
        self::assertStringContainsString("->view('emails.solicitud-traslado')", $mail);
        self::assertStringContainsString('Ubicación de origen', $view);
        self::assertStringContainsString('Ubicación de destino', $view);
        self::assertStringContainsString('Revisar solicitud en SWAFI', $view);
        self::assertStringContainsString("'panel' => 'traslados'", $service);
        self::assertStringContainsString("'solicitud' => \$request->uuid", $service);
        self::assertStringContainsString('NOTIF_TRASLADO_ENVIADA', $service);
        self::assertStringContainsString('NOTIF_TRASLADO_FALLIDA', $service);
    }

    public function test_notification_failure_does_not_rollback_the_request_and_can_be_retried(): void
    {
        $controller = $this->read('app/Http/Controllers/UbicacionInventarioController.php');
        $notification = $this->read('app/Services/TransferNotificationService.php');
        $approvalController = $this->read('app/Http/Controllers/TransferApprovalController.php');
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');

        self::assertStringContainsString("if (\$result['type'] === 'transfer_request')", $controller);
        self::assertStringContainsString('sendAssignment', $controller);
        self::assertStringContainsString("'sent' => false", $notification);
        self::assertStringContainsString('La solicitud se guardó y quedó asignada', $notification);
        self::assertStringContainsString('resendNotification', $approvalController);
        self::assertStringContainsString('ubicacion.traslados.notificar', $routes);
        self::assertStringContainsString("'ubicacion.traslados.notificar'", $middleware);
    }

    public function test_interface_shows_dynamic_approver_combo_and_notification_status(): void
    {
        $view = $this->read('resources/views/swafi/ubicacion.blade.php');
        $partial = $this->read('resources/views/swafi/partials/transfer-approvals.blade.php');
        $controller = $this->read('app/Http/Controllers/UbicacionInventarioController.php');

        self::assertStringContainsString('usuariosAprobadoresTraslado', $controller);
        self::assertStringContainsString("->where('r.nombre', 'Usuario Captura')", $controller);
        self::assertStringContainsString('Usuario Captura responsable de aprobar', $view);
        self::assertStringContainsString('data-planta-id', $view);
        self::assertStringContainsString('syncTransferApprover', $view);
        self::assertStringContainsString('approverUser.required = isCrossPlant', $view);
        self::assertStringContainsString('Aprobador / notificación', $partial);
        self::assertStringContainsString('Correo enviado', $partial);
        self::assertStringContainsString('Reenviar correo', $partial);
    }

    public function test_existing_session_query_inventory_and_export_features_remain_present(): void
    {
        $view = $this->read('resources/views/swafi/ubicacion.blade.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $query = $this->read('public/assets/swafi/js/swafi-query-results.js');

        foreach ([
            'Toma de inventario',
            'Evidencias de inventario',
            'Notificar a Consulta / Auditoría',
            'Exportar CSV',
            'Etiqueta QR',
            'data-swafi-query-panel',
            'data-swafi-query-results',
        ] as $feature) {
            self::assertStringContainsString($feature, $view);
        }

        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("const FOCUS_PARAMETER = 'swafi_focus';", $query);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
