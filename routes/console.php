<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Auditoría programada de almacenamiento SWAFI
|--------------------------------------------------------------------------
|
| El scheduler debe estar habilitado en Laravel Cloud. La auditoría revisa
| avatares, PDF/XML y evidencias contra su metadata y hash SHA-256.
|
*/

if (config('filesystems.swafi_audit_scheduled', true)) {
    Schedule::command('swafi:storage-audit --quiet-success')
        ->dailyAt((string) config('filesystems.swafi_audit_time', '02:30'))
        ->withoutOverlapping(30)
        ->onOneServer();
}

/*
|--------------------------------------------------------------------------
| Reportes programados SWAFI (HU-082)
|--------------------------------------------------------------------------
|
| Laravel Cloud debe ejecutar el scheduler y mantener un worker para la cola
| configurada en SWAFI_SCHEDULED_REPORTS_QUEUE.
|
*/
if (config('swafi.reportes_programados.habilitados', true)) {
    Schedule::command(
        'swafi:dispatch-scheduled-reports --limit=' .
        (int) config('swafi.reportes_programados.limite_lote', 50)
    )
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->onOneServer();
}

/*
|--------------------------------------------------------------------------
| Recordatorios de observaciones de expediente (HU-014)
|--------------------------------------------------------------------------
|
| Se ejecuta una vez al día en la zona horaria configurada. El servicio
| reclama cada observación antes de enviar para evitar duplicados durante
| el mismo día y conserva referencias seguras cuando el correo falla.
|
*/
if (config('swafi.observaciones_recordatorios.habilitados', true)) {
    Schedule::command(
        'swafi:dispatch-observation-reminders --limit=' .
        (int) config('swafi.observaciones_recordatorios.limite_lote', 50)
    )
        ->dailyAt((string) config('swafi.observaciones_recordatorios.hora', '08:00'))
        ->timezone((string) config(
            'swafi.observaciones_recordatorios.zona_horaria',
            'America/Mexico_City'
        ))
        ->withoutOverlapping(30)
        ->onOneServer();
}

