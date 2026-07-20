<?php

namespace App\Console\Commands;

use App\Services\ObservationReminderService;
use Illuminate\Console\Command;

class DispatchObservationRemindersCommand extends Command
{
    protected $signature = 'swafi:dispatch-observation-reminders {--limit= : Número máximo de observaciones por ciclo}';

    protected $description = 'Envía recordatorios de observaciones próximas a vencer o vencidas.';

    public function handle(ObservationReminderService $reminders): int
    {
        $limit = $this->option('limit');
        $summary = $reminders->dispatchDue(
            is_numeric($limit) ? (int) $limit : null
        );

        $this->info(sprintf(
            'SWAFI procesó %d observación(es): %d enviada(s), %d fallida(s) y %d omitida(s).',
            $summary['procesadas'],
            $summary['enviadas'],
            $summary['fallidas'],
            $summary['omitidas']
        ));

        return $summary['fallidas'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
