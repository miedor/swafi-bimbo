<?php

namespace App\Console\Commands;

use App\Services\ScheduledReportService;
use Illuminate\Console\Command;

class DispatchScheduledReportsCommand extends Command
{
    protected $signature = 'swafi:dispatch-scheduled-reports {--limit= : Número máximo de programaciones por ciclo}';

    protected $description = 'Encola los reportes SWAFI cuya fecha de ejecución ya venció.';

    public function handle(ScheduledReportService $scheduledReports): int
    {
        $limit = $this->option('limit');
        $processed = $scheduledReports->dispatchDue(
            is_numeric($limit) ? (int) $limit : null
        );

        $this->info(sprintf(
            'SWAFI encoló %d reporte(s) programado(s).',
            $processed
        ));

        return self::SUCCESS;
    }
}
