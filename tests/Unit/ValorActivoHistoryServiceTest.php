<?php

namespace Tests\Unit;

use App\Services\ValorActivoHistoryService;
use PHPUnit\Framework\TestCase;

class ValorActivoHistoryServiceTest extends TestCase
{
    private ValorActivoHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ValorActivoHistoryService();
    }

    public function test_build_changes_formats_business_values_and_ignores_technical_fields(): void
    {
        $changes = $this->service->buildChanges(
            [
                'valor_fiscal' => '100000.00',
                'estatus_contable' => 'vigente',
                'registrado_por' => 10,
                'password' => 'valor-que-no-debe-exponerse',
            ],
            [
                'valor_fiscal' => '125000.50',
                'estatus_contable' => 'en_revision',
                'registrado_por' => 11,
                'password' => 'otro-valor',
            ]
        );

        self::assertSame([
            [
                'field' => 'valor_fiscal',
                'label' => 'Valor fiscal',
                'before' => '$ 100,000.00',
                'after' => '$ 125,000.50',
            ],
            [
                'field' => 'estatus_contable',
                'label' => 'Estatus contable',
                'before' => 'Vigente',
                'after' => 'En Revision',
            ],
        ], $changes);
    }

    public function test_equivalent_numeric_values_do_not_generate_false_changes(): void
    {
        $changes = $this->service->buildChanges(
            [
                'valor_fiscal' => '100.00',
                'tipo_cambio' => '1.000000',
            ],
            [
                'valor_fiscal' => 100,
                'tipo_cambio' => 1,
            ]
        );

        self::assertSame([], $changes);
    }

    public function test_reference_depreciation_fields_are_formatted_as_business_changes(): void
    {
        $changes = $this->service->buildChanges(
            [
                'metodo_depreciacion' => null,
                'fecha_inicio_depreciacion' => null,
                'valor_residual' => '0.00',
                'depreciacion_estimada' => null,
                'valor_en_libros_estimado' => null,
            ],
            [
                'metodo_depreciacion' => 'linea_recta',
                'fecha_inicio_depreciacion' => '2026-01-31',
                'valor_residual' => '10000.00',
                'depreciacion_estimada' => '20000.00',
                'valor_en_libros_estimado' => '80000.00',
            ]
        );

        self::assertSame('Método de depreciación referencial', $changes[0]['label']);
        self::assertSame('Linea Recta', $changes[0]['after']);
        self::assertContains([
            'field' => 'valor_residual',
            'label' => 'Valor residual',
            'before' => '$ 0.00',
            'after' => '$ 10,000.00',
        ], $changes);
        self::assertContains([
            'field' => 'valor_en_libros_estimado',
            'label' => 'Valor en libros estimado',
            'before' => 'Sin valor',
            'after' => '$ 80,000.00',
        ], $changes);
    }

    public function test_malformed_json_is_handled_without_throwing(): void
    {
        self::assertSame([], $this->service->decodePayload('{json-invalido'));
        self::assertSame([], $this->service->decodePayload(null));
        self::assertSame(['valor_fiscal' => 10], $this->service->decodePayload('{"valor_fiscal":10}'));
    }

    public function test_user_content_remains_plain_text_for_blade_to_escape(): void
    {
        $changes = $this->service->buildChanges(
            ['motivo_cambio' => 'Valor anterior'],
            ['motivo_cambio' => '<script>alert("xss")</script>']
        );

        self::assertCount(1, $changes);
        self::assertSame('<script>alert("xss")</script>', $changes[0]['after']);
    }

    public function test_unknown_action_has_a_readable_fallback_label(): void
    {
        self::assertSame(
            'Ajuste Manual Especial',
            $this->service->actionLabel('AJUSTE_MANUAL_ESPECIAL')
        );
    }
}
