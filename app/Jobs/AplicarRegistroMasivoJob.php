<?php

namespace App\Jobs;

use App\Models\ImportacionMasiva;
use App\Services\RegistroMasivoService;
use App\Services\SafeExceptionReporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class AplicarRegistroMasivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * La carga masiva conserva su transacción integral por lote; por ello el
     * worker dispone de una ventana amplia sin depender del timeout HTTP.
     */
    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $importacionId,
        private readonly ?int $userId
    ) {
        $this->onConnection('bulk_imports');
        $this->onQueue('swafi-imports');
    }

    public function handle(
        RegistroMasivoService $service,
        SafeExceptionReporter $safeExceptions
    ): void {
        $batch = ImportacionMasiva::query()->find($this->importacionId);

        if (!$batch) {
            return;
        }

        if ($batch->estado === 'aplicada') {
            $batch->update([
                'procesamiento_estado' => 'completado',
                'procesamiento_porcentaje' => 100,
                'procesamiento_finalizado_at' => $batch->procesamiento_finalizado_at ?: now(),
                'procesamiento_error_referencia' => null,
            ]);

            return;
        }

        $claimed = ImportacionMasiva::query()
            ->whereKey($batch->id)
            ->where('estado', 'previsualizada')
            ->where('procesamiento_estado', 'pendiente')
            ->update([
                'procesamiento_estado' => 'procesando',
                'procesamiento_iniciado_at' => now(),
                'procesamiento_finalizado_at' => null,
                'procesamiento_porcentaje' => 5,
                'procesamiento_error_referencia' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        try {
            $batch = ImportacionMasiva::query()->findOrFail($this->importacionId);
            $summary = $service->aplicar($batch, $this->userId);
            $freshBatch = ImportacionMasiva::query()->find($this->importacionId);

            if (!$freshBatch) {
                return;
            }

            $processingSummary = is_array($freshBatch->resumen)
                ? $freshBatch->resumen
                : [];
            $processingSummary['procesamiento'] = [
                'estado' => 'completado',
                'mensaje' => 'La carga masiva terminó correctamente en segundo plano.',
                'resultado' => $summary,
                'finalizado_at' => now()->toIso8601String(),
            ];

            $freshBatch->update([
                'procesamiento_estado' => 'completado',
                'procesamiento_porcentaje' => 100,
                'procesamiento_finalizado_at' => now(),
                'procesamiento_error_referencia' => null,
                'resumen' => $processingSummary,
            ]);
        } catch (Throwable $exception) {
            $reference = $safeExceptions->warning(
                $exception,
                'bulk_import_background_apply',
                [
                    'batch_id' => $this->importacionId,
                    'user_id' => $this->userId,
                ]
            );

            $this->markAsFailed($reference);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = ImportacionMasiva::query()->find($this->importacionId);

        if (!$batch || $batch->procesamiento_estado === 'completado') {
            return;
        }

        if ($batch->procesamiento_estado !== 'error') {
            $batch->update([
                'procesamiento_estado' => 'error',
                'procesamiento_finalizado_at' => now(),
                'procesamiento_porcentaje' => 0,
            ]);
        }
    }

    private function markAsFailed(string $reference): void
    {
        DB::transaction(function () use ($reference): void {
            $batch = ImportacionMasiva::query()
                ->whereKey($this->importacionId)
                ->lockForUpdate()
                ->first();

            if (!$batch || $batch->procesamiento_estado === 'completado') {
                return;
            }

            $summary = is_array($batch->resumen) ? $batch->resumen : [];
            $summary['procesamiento'] = [
                'estado' => 'error',
                'mensaje' => 'No fue posible terminar la carga en segundo plano. El lote puede revisarse y reintentarse.',
                'referencia' => $reference,
                'finalizado_at' => now()->toIso8601String(),
            ];

            $batch->update([
                'procesamiento_estado' => 'error',
                'procesamiento_finalizado_at' => now(),
                'procesamiento_porcentaje' => 0,
                'procesamiento_error_referencia' => $reference,
                'resumen' => $summary,
            ]);
        });
    }
}
