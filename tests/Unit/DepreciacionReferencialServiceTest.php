<?php

namespace Tests\Unit;

use App\Services\DepreciacionReferencialService;
use DomainException;
use PHPUnit\Framework\TestCase;

class DepreciacionReferencialServiceTest extends TestCase
{
    private DepreciacionReferencialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DepreciacionReferencialService();
    }

    public function test_linea_recta_returns_zero_before_depreciation_starts(): void
    {
        $result = $this->service->calculate(
            method: 'linea_recta',
            financialValue: 100000,
            residualValue: 10000,
            usefulLifeMonths: 60,
            startDate: '2026-07-01',
            cutoffDate: '2026-07-01'
        );

        self::assertSame(0.0, $result['depreciacion_estimada']);
        self::assertSame(100000.0, $result['valor_en_libros_estimado']);
        self::assertSame(0.0, $result['porcentaje_depreciado']);
    }

    public function test_linea_recta_never_depreciates_below_the_residual_value(): void
    {
        $result = $this->service->calculate(
            method: 'linea_recta',
            financialValue: 100000,
            residualValue: 10000,
            usefulLifeMonths: 12,
            startDate: '2025-01-31',
            cutoffDate: '2027-01-31'
        );

        self::assertSame(90000.0, $result['depreciacion_estimada']);
        self::assertSame(10000.0, $result['valor_en_libros_estimado']);
        self::assertSame(100.0, $result['porcentaje_depreciado']);
        self::assertSame('2026-01-31', $result['fecha_fin_vida_util']);
    }

    public function test_month_end_is_calculated_without_date_overflow(): void
    {
        $result = $this->service->calculate(
            method: 'linea_recta',
            financialValue: 12000,
            residualValue: 0,
            usefulLifeMonths: 1,
            startDate: '2026-01-31',
            cutoffDate: '2026-02-28'
        );

        self::assertSame('2026-02-28', $result['fecha_fin_vida_util']);
        self::assertSame(12000.0, $result['depreciacion_estimada']);
        self::assertSame(0.0, $result['valor_en_libros_estimado']);
    }

    public function test_rejects_unimplemented_methods_and_invalid_business_values(): void
    {
        $this->expectException(DomainException::class);

        $this->service->calculate(
            method: 'saldo_decreciente',
            financialValue: 100000,
            residualValue: 0,
            usefulLifeMonths: 60,
            startDate: '2026-01-01',
            cutoffDate: '2026-07-01'
        );
    }

    public function test_rejects_residual_value_above_financial_value(): void
    {
        $this->expectException(DomainException::class);

        $this->service->calculate(
            method: 'linea_recta',
            financialValue: 1000,
            residualValue: 1001,
            usefulLifeMonths: 60,
            startDate: '2026-01-01',
            cutoffDate: '2026-07-01'
        );
    }
}
