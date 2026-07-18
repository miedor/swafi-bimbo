<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use PHPUnit\Framework\TestCase;

class AuditLogServiceTest extends TestCase
{
    public function test_snapshot_export_removes_passwords_tokens_and_secrets(): void
    {
        $service = new AuditLogService();
        $snapshot = json_encode([
            'usuario' => 'captura01',
            'password' => 'NoDebeAparecer',
            'remember_token' => 'NoDebeAparecer',
            'perfil' => [
                'nombre' => 'Usuario Captura',
                'api_key' => 'NoDebeAparecer',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $result = $service->snapshotForExport($snapshot);

        self::assertStringContainsString('Usuario: captura01', $result);
        self::assertStringContainsString('Perfil Nombre: Usuario Captura', $result);
        self::assertStringNotContainsString('NoDebeAparecer', $result);
        self::assertStringNotContainsString('Password', $result);
        self::assertStringNotContainsString('Token', $result);
    }

    public function test_invalid_snapshot_json_is_reported_without_exposing_the_original_content(): void
    {
        $service = new AuditLogService();
        $result = $service->snapshotForExport('{contenido-invalido<script>alert(1)</script>');

        self::assertSame('Estado: Contenido histórico no interpretable.', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    public function test_nested_snapshot_fields_are_flattened_for_auditing(): void
    {
        $service = new AuditLogService();
        $result = $service->snapshotForExport(json_encode([
            'ubicacion' => [
                'planta' => 'Santa María',
                'linea' => 'Empaque 1',
            ],
        ], JSON_UNESCAPED_UNICODE));

        self::assertStringContainsString('Ubicacion Planta: Santa María', $result);
        self::assertStringContainsString('Ubicacion Linea: Empaque 1', $result);
    }

    public function test_filter_summary_includes_only_meaningful_filters(): void
    {
        $service = new AuditLogService();
        $summary = $service->filterSummary([
            'buscar_bitacora' => 'traslado',
            'usuario_bitacora_id' => 15,
            'numero_activo' => '',
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
            'fecha_desde' => '2026-07-01',
            'fecha_hasta' => null,
        ]);

        self::assertStringContainsString('Texto: traslado', $summary);
        self::assertStringContainsString('Usuario ID: 15', $summary);
        self::assertStringContainsString('Módulo: M02 Control fiscal, financiero y ubicación física', $summary);
        self::assertStringContainsString('Desde: 2026-07-01', $summary);
        self::assertStringNotContainsString('Activo:', $summary);
        self::assertStringNotContainsString('Hasta:', $summary);
    }

    public function test_empty_filter_summary_is_explicit(): void
    {
        $service = new AuditLogService();

        self::assertSame('Sin filtros adicionales', $service->filterSummary([]));
    }
}
