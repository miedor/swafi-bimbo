<?php

namespace App\Console\Commands;

use App\Models\DocumentoExpediente;
use App\Services\CfdiValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RevalidarCfdiCommand extends Command
{
    protected $signature = 'swafi:cfdi-revalidar {--solo-pendientes : Procesa únicamente XML sin validación registrada}';

    protected $description = 'Valida los XML CFDI vigentes y recalcula el estatus documental de los expedientes SWAFI.';

    public function handle(CfdiValidationService $service): int
    {
        if (!Schema::hasTable('cfdi_validaciones')) {
            $this->error('La tabla cfdi_validaciones no existe. Ejecuta php artisan migrate --force.');

            return self::FAILURE;
        }

        $query = DocumentoExpediente::query()
            ->whereRaw('UPPER(tipo_documento) = ?', ['XML'])
            ->where('vigente', true)
            ->orderBy('id');

        if ($this->option('solo-pendientes')) {
            $query->whereDoesntHave('cfdiValidacion');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No existen XML pendientes de validación.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $summary = ['validos' => 0, 'observados' => 0, 'invalidos' => 0, 'errores' => 0];

        $query->chunkById(100, function ($documents) use ($service, $bar, &$summary) {
            foreach ($documents as $document) {
                try {
                    $validation = $service->validateDocument($document, null);
                    $key = match ($validation->estatus_validacion) {
                        'valido' => 'validos',
                        'observado' => 'observados',
                        default => 'invalidos',
                    };
                    $summary[$key]++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $summary['errores']++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->table(
            ['Válidos', 'Observados', 'Inválidos', 'Errores técnicos'],
            [[$summary['validos'], $summary['observados'], $summary['invalidos'], $summary['errores']]]
        );

        return $summary['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
