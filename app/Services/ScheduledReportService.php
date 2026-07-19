<?php

namespace App\Services;

use App\Jobs\GenerateScheduledReportJob;
use App\Models\ReporteProgramado;
use App\Models\ReporteProgramadoEjecucion;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduledReportService
{
    public function nextRunAt(
        string $frequency,
        string $localTime,
        string $timezone,
        ?int $weekday = null,
        ?int $monthDay = null,
        DateTimeInterface|string|null $after = null
    ): CarbonImmutable {
        $afterUtc = $after instanceof DateTimeInterface
            ? CarbonImmutable::instance($after)
            : CarbonImmutable::parse($after ?: 'now', 'UTC');

        $afterLocal = $afterUtc->setTimezone($timezone);
        [$hour, $minute] = $this->parseTime($localTime);

        $candidate = match ($frequency) {
            'diaria' => $afterLocal->startOfDay()->setTime($hour, $minute),
            'semanal' => $this->weeklyCandidate($afterLocal, $weekday, $hour, $minute),
            'mensual' => $this->monthlyCandidate($afterLocal, $monthDay, $hour, $minute),
            default => throw new InvalidArgumentException('La frecuencia del reporte programado no es válida.'),
        };

        if ($candidate->lessThanOrEqualTo($afterLocal)) {
            $candidate = match ($frequency) {
                'diaria' => $candidate->addDay(),
                'semanal' => $candidate->addWeek(),
                'mensual' => $candidate->addMonthNoOverflow(),
            };
        }

        return $candidate->utc();
    }

    public function nextRunForSchedule(
        ReporteProgramado $schedule,
        DateTimeInterface|string|null $after = null
    ): CarbonImmutable {
        return $this->nextRunAt(
            frequency: (string) $schedule->frecuencia,
            localTime: (string) $schedule->hora_local,
            timezone: (string) $schedule->zona_horaria,
            weekday: $schedule->dia_semana,
            monthDay: $schedule->dia_mes,
            after: $after
        );
    }

    public function dispatchDue(?int $limit = null): int
    {
        if (!config('swafi.reportes_programados.habilitados', true)) {
            return 0;
        }

        $batchLimit = max(1, min(
            100,
            $limit ?? (int) config('swafi.reportes_programados.limite_lote', 50)
        ));
        $now = CarbonImmutable::now('UTC');

        $executionIds = DB::transaction(function () use ($batchLimit, $now): array {
            $schedules = ReporteProgramado::query()
                ->where('activo', true)
                ->whereNotNull('proxima_ejecucion_at')
                ->where('proxima_ejecucion_at', '<=', $now)
                ->orderBy('proxima_ejecucion_at')
                ->lockForUpdate()
                ->limit($batchLimit)
                ->get();

            $ids = [];

            foreach ($schedules as $schedule) {
                $scheduledFor = CarbonImmutable::instance($schedule->proxima_ejecucion_at)->utc();

                $execution = ReporteProgramadoEjecucion::query()->firstOrCreate(
                    [
                        'reporte_programado_id' => $schedule->id,
                        'scheduled_for' => $scheduledFor,
                    ],
                    [
                        'estado' => 'encolado',
                        'formato' => $schedule->formato,
                        'destinatarios_total' => count($schedule->destinatarios ?? []),
                        'destinatarios_enviados' => [],
                    ]
                );

                $schedule->forceFill([
                    'proxima_ejecucion_at' => $this->nextRunForSchedule(
                        $schedule,
                        $scheduledFor->addSecond()
                    ),
                    'ultimo_estado' => 'encolado',
                    'ultimo_error_referencia' => null,
                ])->save();

                if ($execution->wasRecentlyCreated) {
                    $ids[] = (int) $execution->id;
                }
            }

            return $ids;
        }, 3);

        $recoverableIds = ReporteProgramadoEjecucion::query()
            ->where('estado', 'encolado')
            ->where('created_at', '<=', $now->subMinutes(2))
            ->orderBy('created_at')
            ->limit($batchLimit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $dispatchIds = array_values(array_unique(array_merge($executionIds, $recoverableIds)));
        $queue = (string) config('swafi.reportes_programados.cola', 'reports');

        foreach ($dispatchIds as $executionId) {
            GenerateScheduledReportJob::dispatch($executionId)->onQueue($queue);
        }

        return count($dispatchIds);
    }

    private function weeklyCandidate(
        CarbonImmutable $afterLocal,
        ?int $weekday,
        int $hour,
        int $minute
    ): CarbonImmutable {
        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException('El día de la semana debe estar entre 1 y 7.');
        }

        $daysAhead = ($weekday - $afterLocal->isoWeekday() + 7) % 7;

        return $afterLocal
            ->startOfDay()
            ->addDays($daysAhead)
            ->setTime($hour, $minute);
    }

    private function monthlyCandidate(
        CarbonImmutable $afterLocal,
        ?int $monthDay,
        int $hour,
        int $minute
    ): CarbonImmutable {
        if ($monthDay === null || $monthDay < 1 || $monthDay > 28) {
            throw new InvalidArgumentException('El día del mes debe estar entre 1 y 28.');
        }

        return $afterLocal
            ->startOfMonth()
            ->addDays($monthDay - 1)
            ->setTime($hour, $minute);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTime(string $localTime): array
    {
        if (!preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', trim($localTime), $matches)) {
            throw new InvalidArgumentException('La hora del reporte programado no es válida.');
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('La hora del reporte programado no es válida.');
        }

        return [$hour, $minute];
    }
}
