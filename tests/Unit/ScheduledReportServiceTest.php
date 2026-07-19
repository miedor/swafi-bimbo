<?php

namespace Tests\Unit;

use App\Services\ScheduledReportService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ScheduledReportServiceTest extends TestCase
{
    private ScheduledReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ScheduledReportService();
    }

    public function test_daily_schedule_uses_mexico_city_time_and_moves_to_the_next_day_when_needed(): void
    {
        $beforeLocalTime = $this->service->nextRunAt(
            'diaria',
            '08:00',
            'America/Mexico_City',
            after: CarbonImmutable::parse('2026-07-19 12:00:00', 'UTC')
        );
        $afterLocalTime = $this->service->nextRunAt(
            'diaria',
            '08:00',
            'America/Mexico_City',
            after: CarbonImmutable::parse('2026-07-19 15:00:00', 'UTC')
        );

        self::assertSame('2026-07-19 14:00:00', $beforeLocalTime->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-20 14:00:00', $afterLocalTime->format('Y-m-d H:i:s'));
    }

    public function test_weekly_schedule_selects_the_requested_iso_weekday(): void
    {
        $nextRun = $this->service->nextRunAt(
            'semanal',
            '09:30',
            'America/Mexico_City',
            weekday: 1,
            after: CarbonImmutable::parse('2026-07-19 18:00:00', 'UTC')
        );

        self::assertSame('2026-07-20 15:30:00', $nextRun->format('Y-m-d H:i:s'));
        self::assertSame(1, $nextRun->setTimezone('America/Mexico_City')->isoWeekday());
    }

    public function test_monthly_schedule_limits_days_to_existing_dates(): void
    {
        $nextRun = $this->service->nextRunAt(
            'mensual',
            '07:15',
            'America/Mexico_City',
            monthDay: 28,
            after: CarbonImmutable::parse('2026-02-28 15:00:00', 'UTC')
        );

        self::assertSame('2026-03-28 13:15:00', $nextRun->format('Y-m-d H:i:s'));
    }
}
