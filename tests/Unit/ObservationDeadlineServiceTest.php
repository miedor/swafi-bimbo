<?php

namespace Tests\Unit;

use App\Services\ObservationDeadlineService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ObservationDeadlineServiceTest extends TestCase
{
    private ObservationDeadlineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ObservationDeadlineService();
    }

    public function test_open_observation_is_classified_as_overdue_when_deadline_has_passed(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Mexico_City');

        self::assertSame(
            'vencida',
            $this->service->state('2026-07-19', 'abierta', $now, 2)
        );
        self::assertSame(-1, $this->service->daysRemaining('2026-07-19', $now));
        self::assertSame('danger', $this->service->badgeClass('vencida'));
    }

    public function test_due_today_and_due_soon_states_are_distinguished(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Mexico_City');

        self::assertSame(
            'vence_hoy',
            $this->service->state('2026-07-20', 'en_atencion', $now, 2)
        );
        self::assertSame(
            'por_vencer',
            $this->service->state('2026-07-22', 'rechazada', $now, 2)
        );
        self::assertTrue(
            $this->service->isReminderEligible('2026-07-22', 'rechazada', $now, 2)
        );
    }

    public function test_future_deadline_outside_the_reminder_window_remains_in_time(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Mexico_City');

        self::assertSame(
            'en_plazo',
            $this->service->state('2026-07-25', 'abierta', $now, 2)
        );
        self::assertFalse(
            $this->service->isReminderEligible('2026-07-25', 'abierta', $now, 2)
        );
    }

    public function test_attended_observation_waits_for_validation_without_more_reminders(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Mexico_City');

        self::assertSame(
            'pendiente_validacion',
            $this->service->state('2026-07-19', 'atendida', $now, 2)
        );
        self::assertFalse(
            $this->service->isReminderEligible('2026-07-19', 'atendida', $now, 2)
        );
        self::assertSame(
            'Pendiente de validación',
            $this->service->label('pendiente_validacion')
        );
    }

    public function test_closed_or_cancelled_observations_are_finalized(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Mexico_City');

        self::assertSame(
            'finalizada',
            $this->service->state('2026-07-19', 'cerrada', $now, 2)
        );
        self::assertSame(
            'finalizada',
            $this->service->state('2026-07-19', 'cancelada', $now, 2)
        );
    }
}
