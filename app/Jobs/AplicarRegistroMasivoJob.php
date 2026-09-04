<?php

namespace App\Jobs;

use App\Models\ImportacionMasiva;
use App\Services\RegistroMasivoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AplicarRegistroMasivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        private readonly int $importacionId,
        private readonly ?int $userId
    ) {
    }

    public function handle(RegistroMasivoService $service): void
    {
        $batch = ImportacionMasiva::findOrFail($this->importacionId);

        $summary = $service->aplicar($batch, $this->userId);

        $batch->update([
            'estado' => 'aplicada',
            'procesamiento_porcentaje' => 100,
            'procesamiento_finalizado_at' => now(),
            'resumen' => array_merge($batch->fresh()->resumen ?? [], [
                'procesamiento' => [
                    'estado' => 'completado',
                    'mensaje' => 'La carga masiva terminó correctamente.',
                    'resultado' => $summary,
                ],
            ]),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $batch = ImportacionMasiva::find($this->importacionId);

        if (!$batch) {
            return;
        }

        $batch->update([
            'estado' => 'error',
            'procesamiento_finalizado_at' => now(),
            'resumen' => array_merge($batch->resumen ?? [], [
                'procesamiento' => [
                    'estado' => 'error',
                    'mensaje' => 'La carga masiva presentó un error durante el procesamiento.',
                ],
            ]),
        ]);
    }
}
