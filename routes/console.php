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
