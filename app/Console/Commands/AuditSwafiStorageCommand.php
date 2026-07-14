<?php

namespace App\Console\Commands;

use App\Services\SwafiStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AuditSwafiStorageCommand extends Command
{
    protected $signature = 'swafi:storage-audit
        {--disk= : Disco alternativo donde se buscarán copias}
        {--repair-metadata : Corrige únicamente la metadata cuando existe una copia íntegra}
        {--quiet-success : No muestra los registros correctos}';

    protected $description = 'Audita la existencia e integridad de avatares, documentos y evidencias almacenados por SWAFI.';

    public function handle(SwafiStorageService $storageService): int
    {
        $diskAlternativo = trim((string) $this->option('disk'));
        $repararMetadata = (bool) $this->option('repair-metadata');
        $silenciarCorrectos = (bool) $this->option('quiet-success');

        $estadisticas = [
            'revisados' => 0,
            'correctos' => 0,
            'reparados' => 0,
            'faltantes' => 0,
            'hash_invalidos' => 0,
            'errores' => 0,
        ];

        $this->auditarAvatares(
            $storageService,
            $diskAlternativo,
            $repararMetadata,
            $silenciarCorrectos,
            $estadisticas
        );

        $this->auditarDocumentos(
            $storageService,
            $diskAlternativo,
            $repararMetadata,
            $silenciarCorrectos,
            $estadisticas
        );

        $this->auditarEvidencias(
            $storageService,
            $diskAlternativo,
            $repararMetadata,
            $silenciarCorrectos,
            $estadisticas
        );

        $this->newLine();

        $this->table(
            [
                'Revisados',
                'Correctos',
                'Reparados',
                'Faltantes',
                'Hash inválido',
                'Errores',
            ],
            [[
                $estadisticas['revisados'],
                $estadisticas['correctos'],
                $estadisticas['reparados'],
                $estadisticas['faltantes'],
                $estadisticas['hash_invalidos'],
                $estadisticas['errores'],
            ]]
        );

        if (
            $estadisticas['faltantes'] > 0
            || $estadisticas['hash_invalidos'] > 0
            || $estadisticas['errores'] > 0
        ) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function auditarAvatares(
        SwafiStorageService $storageService,
        string $diskAlternativo,
        bool $repararMetadata,
        bool $silenciarCorrectos,
        array &$estadisticas
    ): void {
        if (
            !Schema::hasTable('users')
            || !Schema::hasColumn('users', 'avatar_path')
        ) {
            return;
        }

        DB::table('users')
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($usuarios) use (
                $storageService,
                $diskAlternativo,
                $repararMetadata,
                $silenciarCorrectos,
                &$estadisticas
            ): void {
                foreach ($usuarios as $usuario) {
                    $estadisticas['revisados']++;

                    $disk = $usuario->avatar_disk
                        ?? config('filesystems.swafi_legacy_disk', 'local');

                    $path = trim((string) $usuario->avatar_path);

                    try {
                        if ($this->archivoCorrecto(
                            $storageService,
                            $disk,
                            $path,
                            null
                        )) {
                            $estadisticas['correctos']++;

                            if (!$silenciarCorrectos) {
                                $this->line(
                                    "[OK] avatar usuario {$usuario->id} · {$disk} · {$path}"
                                );
                            }

                            continue;
                        }

                        if (
                            $this->repararMetadataSiEsPosible(
                                'users',
                                (int) $usuario->id,
                                'avatar_disk',
                                $path,
                                null,
                                $diskAlternativo,
                                $repararMetadata,
                                $storageService
                            )
                        ) {
                            $estadisticas['reparados']++;

                            $this->info(
                                "[REPARADO] avatar usuario {$usuario->id} · {$diskAlternativo}"
                            );

                            continue;
                        }

                        $estadisticas['faltantes']++;

                        $this->warn(
                            "[INCIDENCIA] avatar usuario {$usuario->id}: "
                            ."El archivo no fue localizado en el disco [{$disk}]."
                        );
                    } catch (Throwable $exception) {
                        $estadisticas['errores']++;

                        $this->error(
                            "[ERROR] avatar usuario {$usuario->id}: "
                            .$exception->getMessage()
                        );
                    }
                }
            }, 'id');
    }

    private function auditarDocumentos(
        SwafiStorageService $storageService,
        string $diskAlternativo,
        bool $repararMetadata,
        bool $silenciarCorrectos,
        array &$estadisticas
    ): void {
        if (!Schema::hasTable('documentos_expediente')) {
            return;
        }

        DB::table('documentos_expediente')
            ->where('vigente', true)
            ->whereNotNull('ruta_archivo')
            ->where('ruta_archivo', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($documentos) use (
                $storageService,
                $diskAlternativo,
                $repararMetadata,
                $silenciarCorrectos,
                &$estadisticas
            ): void {
                foreach ($documentos as $documento) {
                    $estadisticas['revisados']++;

                    $disk = $documento->storage_disk
                        ?? config('filesystems.swafi_legacy_disk', 'local');

                    $path = trim((string) $documento->ruta_archivo);
                    $hash = $documento->hash_sha256 ?: null;

                    try {
                        if (!Storage::disk($disk)->exists($path)) {
                            if (
                                $this->repararMetadataSiEsPosible(
                                    'documentos_expediente',
                                    (int) $documento->id,
                                    'storage_disk',
                                    $path,
                                    $hash,
                                    $diskAlternativo,
                                    $repararMetadata,
                                    $storageService
                                )
                            ) {
                                $estadisticas['reparados']++;

                                $this->info(
                                    "[REPARADO] documento {$documento->id} · {$diskAlternativo}"
                                );

                                continue;
                            }

                            $estadisticas['faltantes']++;

                            $this->warn(
                                "[INCIDENCIA] documento {$documento->id}: "
                                ."El archivo no fue localizado en el disco [{$disk}]."
                            );

                            continue;
                        }

                        if (
                            $hash !== null
                            && !$storageService->verifyHash($disk, $path, $hash)
                        ) {
                            $estadisticas['hash_invalidos']++;

                            $this->warn(
                                "[INCIDENCIA] documento {$documento->id}: "
                                .'El hash SHA-256 no coincide.'
                            );

                            continue;
                        }

                        $estadisticas['correctos']++;

                        if (!$silenciarCorrectos) {
                            $this->line(
                                "[OK] documento {$documento->id} · {$disk} · {$path}"
                            );
                        }
                    } catch (Throwable $exception) {
                        $estadisticas['errores']++;

                        $this->error(
                            "[ERROR] documento {$documento->id}: "
                            .$exception->getMessage()
                        );
                    }
                }
            }, 'id');
    }

    private function auditarEvidencias(
        SwafiStorageService $storageService,
        string $diskAlternativo,
        bool $repararMetadata,
        bool $silenciarCorrectos,
        array &$estadisticas
    ): void {
        if (!Schema::hasTable('inventario_evidencias')) {
            return;
        }

        DB::table('inventario_evidencias')
            ->where('vigente', true)
            ->whereNotNull('ruta_archivo')
            ->where('ruta_archivo', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($evidencias) use (
                $storageService,
                $diskAlternativo,
                $repararMetadata,
                $silenciarCorrectos,
                &$estadisticas
            ): void {
                foreach ($evidencias as $evidencia) {
                    $estadisticas['revisados']++;

                    $disk = $evidencia->storage_disk
                        ?? config('filesystems.swafi_legacy_disk', 'local');

                    $path = trim((string) $evidencia->ruta_archivo);
                    $hash = $evidencia->hash_sha256 ?: null;

                    try {
                        if (!Storage::disk($disk)->exists($path)) {
                            if (
                                $this->repararMetadataSiEsPosible(
                                    'inventario_evidencias',
                                    (int) $evidencia->id,
                                    'storage_disk',
                                    $path,
                                    $hash,
                                    $diskAlternativo,
                                    $repararMetadata,
                                    $storageService
                                )
                            ) {
                                $estadisticas['reparados']++;

                                $this->info(
                                    "[REPARADO] evidencia {$evidencia->id} · {$diskAlternativo}"
                                );

                                continue;
                            }

                            $estadisticas['faltantes']++;

                            $this->warn(
                                "[INCIDENCIA] evidencia {$evidencia->id}: "
                                ."El archivo no fue localizado en el disco [{$disk}]."
                            );

                            continue;
                        }

                        if (
                            $hash !== null
                            && !$storageService->verifyHash($disk, $path, $hash)
                        ) {
                            $estadisticas['hash_invalidos']++;

                            $this->warn(
                                "[INCIDENCIA] evidencia {$evidencia->id}: "
                                .'El hash SHA-256 no coincide.'
                            );

                            continue;
                        }

                        $estadisticas['correctos']++;

                        if (!$silenciarCorrectos) {
                            $this->line(
                                "[OK] evidencia {$evidencia->id} · {$disk} · {$path}"
                            );
                        }
                    } catch (Throwable $exception) {
                        $estadisticas['errores']++;

                        $this->error(
                            "[ERROR] evidencia {$evidencia->id}: "
                            .$exception->getMessage()
                        );
                    }
                }
            }, 'id');
    }

    private function archivoCorrecto(
        SwafiStorageService $storageService,
        string $disk,
        string $path,
        ?string $hash
    ): bool {
        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            return false;
        }

        if ($hash === null || $hash === '') {
            return true;
        }

        return $storageService->verifyHash($disk, $path, $hash);
    }

    private function repararMetadataSiEsPosible(
        string $tabla,
        int $id,
        string $columnaDisco,
        string $path,
        ?string $hash,
        string $diskAlternativo,
        bool $repararMetadata,
        SwafiStorageService $storageService
    ): bool {
        if (
            !$repararMetadata
            || $diskAlternativo === ''
            || !array_key_exists(
                $diskAlternativo,
                config('filesystems.disks', [])
            )
        ) {
            return false;
        }

        if (!Storage::disk($diskAlternativo)->exists($path)) {
            return false;
        }

        if (
            $hash !== null
            && $hash !== ''
            && !$storageService->verifyHash(
                $diskAlternativo,
                $path,
                $hash
            )
        ) {
            return false;
        }

        DB::table($tabla)
            ->where('id', $id)
            ->update([
                $columnaDisco => $diskAlternativo,
                'updated_at' => now(),
            ]);

        return true;
    }
}
