<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class ObservationDeadlineService
{
    /**
     * @var array<int, string>
     */
    public const TRACKED_STATUSES = [
        'abierta',
        'en_atencion',
        'atendida',
        'rechazada',
    ];

    /**
     * @var array<int, string>
     */
    public const REMINDER_STATUSES = [
        'abierta',
        'en_atencion',
        'rechazada',
    ];

    public function state(
        DateTimeInterface|string|null $deadline,
        string $status,
        CarbonInterface|DateTimeInterface|null $now = null,
        int $dueSoonDays = 2
    ): string {
        if ($status === 'atendida') {
            return 'pendiente_validacion';
        }

        if (!in_array($status, self::TRACKED_STATUSES, true)) {
            return 'finalizada';
        }

        if ($deadline === null || trim((string) $deadline) === '') {
            return 'sin_fecha';
        }

        $daysRemaining = $this->daysRemaining($deadline, $now);

        if ($daysRemaining < 0) {
            return 'vencida';
        }

        if ($daysRemaining === 0) {
            return 'vence_hoy';
        }

        if ($daysRemaining <= max(0, $dueSoonDays)) {
            return 'por_vencer';
        }

        return 'en_plazo';
    }

    public function daysRemaining(
        DateTimeInterface|string $deadline,
        CarbonInterface|DateTimeInterface|null $now = null
    ): int {
        $today = $this->toImmutable($now ?? CarbonImmutable::now())->startOfDay();
        $dueDate = $this->toImmutable($deadline)->startOfDay();

        return (int) $today->diffInDays($dueDate, false);
    }

    public function label(string $state, ?int $daysRemaining = null): string
    {
        return match ($state) {
            'vencida' => $daysRemaining === null
                ? 'Vencida'
                : 'Vencida hace ' . abs($daysRemaining) . ' día(s)',
            'vence_hoy' => 'Vence hoy',
            'por_vencer' => $daysRemaining === null
                ? 'Por vencer'
                : 'Vence en ' . $daysRemaining . ' día(s)',
            'en_plazo' => $daysRemaining === null
                ? 'En plazo'
                : 'En plazo · ' . $daysRemaining . ' día(s)',
            'pendiente_validacion' => 'Pendiente de validación',
            'finalizada' => 'Finalizada',
            default => 'Sin fecha compromiso',
        };
    }

    public function badgeClass(string $state): string
    {
        return match ($state) {
            'vencida' => 'danger',
            'vence_hoy', 'por_vencer' => 'warn',
            'en_plazo', 'pendiente_validacion', 'finalizada' => 'ok',
            default => '',
        };
    }

    public function isReminderEligible(
        DateTimeInterface|string|null $deadline,
        string $status,
        CarbonInterface|DateTimeInterface|null $now = null,
        int $dueSoonDays = 2
    ): bool {
        return in_array(
            $this->state($deadline, $status, $now, $dueSoonDays),
            ['vencida', 'vence_hoy', 'por_vencer'],
            true
        );
    }

    private function toImmutable(DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($value);
    }
}
