<?php

namespace App\Console\Commands;

use App\Services\SwafiStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MigrateSwafiStorageCommand extends Command
{
    protected $signature = 'swafi:storage-migrate
        {--from=local : Disco de origen para registros históricos sin metadata}
        {--to= : Disco de destino; usa SWAFI_STORAGE_DISK cuando se omite}
        {--type=all : all, avatars, documents o evidences}
        {--limit=0 : Máximo de registros a procesar; 0 significa todos}
        {--dry-run : Solo valida y muestra lo que se migraría}
        {--delete-source : Elimina el archivo origen después de validar la copia}';

    protected $description = 'Migra avatares, documentos y evidencias SWAFI entre discos con verificación SHA-256.';

    public function handle(SwafiStorageService $storage): int
    {
        $from = $storage->resolveDisk((string) $this->option('from'));
        $to = $storage->resolveDisk((string) ($this->option('to') ?: $storage->defaultDisk()));
        $type = strtolower(trim((string) $this->option('type')));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');

        if (!in_array($type, ['all', 'avatars', 'documents', 'evidences'], true)) {
            $this->error('El tipo debe ser all, avatars, documents o evidences.');

            return self::FAILURE;
        }

        $this->info('Migración segura de almacenamiento SWAFI');
        $this->line("Origen histórico: {$from}");
        $this->line("Destino: {$to}");
        $this->line('Modo: ' . ($dryRun ? 'simulación' : 'ejecución'));

        $summary = [
            'procesados' => 0,
            'migrados' => 0,
            'ya_existian' => 0,
            'omitidos' => 0,
            'errores' => 0,
        ];

        $remaining = $limit;

        if (in_array($type, ['all', 'avatars'], true) && ($limit === 0 || $remaining > 0)) {
            $this->migrateAvatars($storage, $from, $to, $dryRun, $deleteSource, $remaining, $summary);
        }

        if (in_array($type, ['all', 'documents'], true) && ($limit === 0 || $remaining > 0)) {
            $this->migrateDocuments($storage, $from, $to, $dryRun, $deleteSource, $remaining, $summary);
        }

        if (in_array($type, ['all', 'evidences'], true) && ($limit === 0 || $remaining > 0)) {
            $this->migrateEvidences($storage, $from, $to, $dryRun, $deleteSource, $remaining, $summary);
        }

        $this->newLine();
        $this->table(
            ['Procesados', 'Migrados', 'Ya existentes', 'Omitidos', 'Errores'],
            [[
                $summary['procesados'],
                $summary['migrados'],
                $summary['ya_existian'],
                $summary['omitidos'],
                $summary['errores'],
            ]]
        );

        if (!$dryRun) {
            $this->registerAudit($from, $to, $summary);
        }

        return $summary['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateAvatars(
        SwafiStorageService $storage,
        string $from,
        string $to,
        bool $dryRun,
        bool $deleteSource,
        int &$remaining,
        array &$summary
    ): void {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'avatar_disk')) {
            $this->warn('Se omiten avatares: falta ejecutar la migración de metadata de almacenamiento.');
            return;
        }

        $query = DB::table('users')
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '<>', '')
            ->orderBy('id');

        foreach ($query->cursor() as $row) {
            if (!$this->canProcess($remaining)) {
                break;
            }

            $this->migrateRecord(
                storage: $storage,
                label: "avatar usuario {$row->id}",
                table: 'users',
                id: (int) $row->id,
                pathColumn: 'avatar_path',
                diskColumn: 'avatar_disk',
                sourceDisk: $row->avatar_disk ?: $from,
                sourcePath: (string) $row->avatar_path,
                targetDisk: $to,
                expectedHash: null,
                dryRun: $dryRun,
                deleteSource: $deleteSource,
                summary: $summary
            );
        }
    }

    private function migrateDocuments(
        SwafiStorageService $storage,
        string $from,
        string $to,
        bool $dryRun,
        bool $deleteSource,
        int &$remaining,
        array &$summary
    ): void {
        if (!Schema::hasTable('documentos_expediente') || !Schema::hasColumn('documentos_expediente', 'storage_disk')) {
            $this->warn('Se omiten documentos: falta ejecutar la migración de metadata de almacenamiento.');
            return;
        }

        $query = DB::table('documentos_expediente')
            ->whereNotNull('ruta_archivo')
            ->where('ruta_archivo', '<>', '')
            ->orderBy('id');

        foreach ($query->cursor() as $row) {
            if (!$this->canProcess($remaining)) {
                break;
            }

            $this->migrateRecord(
                storage: $storage,
                label: "documento {$row->id}",
                table: 'documentos_expediente',
                id: (int) $row->id,
                pathColumn: 'ruta_archivo',
                diskColumn: 'storage_disk',
                sourceDisk: $row->storage_disk ?: $from,
                sourcePath: (string) $row->ruta_archivo,
                targetDisk: $to,
                expectedHash: $row->hash_sha256 ?: null,
                dryRun: $dryRun,
                deleteSource: $deleteSource,
                summary: $summary
            );
        }
    }

    private function migrateEvidences(
        SwafiStorageService $storage,
        string $from,
        string $to,
        bool $dryRun,
        bool $deleteSource,
        int &$remaining,
        array &$summary
    ): void {
        if (!Schema::hasTable('inventario_evidencias') || !Schema::hasColumn('inventario_evidencias', 'storage_disk')) {
            $this->warn('Se omiten evidencias: falta ejecutar la migración de metadata de almacenamiento.');
            return;
        }

        $query = DB::table('inventario_evidencias')
            ->whereNotNull('ruta_archivo')
            ->where('ruta_archivo', '<>', '')
            ->orderBy('id');

        foreach ($query->cursor() as $row) {
            if (!$this->canProcess($remaining)) {
                break;
            }

            $this->migrateRecord(
                storage: $storage,
                label: "evidencia {$row->id}",
                table: 'inventario_evidencias',
                id: (int) $row->id,
                pathColumn: 'ruta_archivo',
                diskColumn: 'storage_disk',
                sourceDisk: $row->storage_disk ?: $from,
                sourcePath: (string) $row->ruta_archivo,
                targetDisk: $to,
                expectedHash: $row->hash_sha256 ?: null,
                dryRun: $dryRun,
                deleteSource: $deleteSource,
                summary: $summary
            );
        }
    }

    private function migrateRecord(
        SwafiStorageService $storage,
        string $label,
        string $table,
        int $id,
        string $pathColumn,
        string $diskColumn,
        string $sourceDisk,
        string $sourcePath,
        string $targetDisk,
        ?string $expectedHash,
        bool $dryRun,
        bool $deleteSource,
        array &$summary
    ): void {
        $summary['procesados']++;
        $sourceDisk = $storage->recordDisk($sourceDisk);

        try {
            $targetValidation = $storage->validate($targetDisk, $sourcePath, $expectedHash);

            if ($targetValidation['ok']) {
                if (!$dryRun) {
                    DB::table($table)->where('id', $id)->update([
                        $diskColumn => $targetDisk,
                        'updated_at' => now(),
                    ]);
                }

                $summary['ya_existian']++;
                $this->line("[OK] {$label}: ya existe y es íntegro en {$targetDisk}.");
                return;
            }

            $sourceValidation = $storage->validate($sourceDisk, $sourcePath, $expectedHash);

            if (!$sourceValidation['ok']) {
                throw new RuntimeException($sourceValidation['message'] ?: 'No se encontró un origen íntegro.');
            }

            if ($dryRun) {
                $summary['migrados']++;
                $this->line("[SIMULA] {$label}: {$sourceDisk} → {$targetDisk} / {$sourcePath}");
                return;
            }

            $result = $storage->copyBetweenDisks(
                sourceDisk: $sourceDisk,
                sourcePath: $sourcePath,
                targetDisk: $targetDisk,
                targetPath: $sourcePath,
                expectedHash: $expectedHash
            );

            DB::transaction(function () use ($table, $id, $diskColumn, $targetDisk): void {
                DB::table($table)->where('id', $id)->update([
                    $diskColumn => $targetDisk,
                    'updated_at' => now(),
                ]);
            });

            if ($deleteSource && ($sourceDisk !== $targetDisk)) {
                $storage->delete($sourceDisk, $sourcePath);
            }

            $summary['migrados']++;
            $this->line("[MIGRADO] {$label}: {$result['hash_sha256']}");
        } catch (\Throwable $exception) {
            $summary['errores']++;
            $this->error("[ERROR] {$label}: {$exception->getMessage()}");
        }
    }

    private function canProcess(int &$remaining): bool
    {
        if ((int) $this->option('limit') === 0) {
            return true;
        }

        if ($remaining <= 0) {
            return false;
        }

        $remaining--;

        return true;
    }

    private function registerAudit(string $from, string $to, array $summary): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'MIGRACION_STORAGE',
                'tabla_afectada' => null,
                'registro_clave' => null,
                'antes' => json_encode(['origen' => $from], JSON_UNESCAPED_UNICODE),
                'despues' => json_encode(['destino' => $to, 'resumen' => $summary], JSON_UNESCAPED_UNICODE),
                'ip' => 'artisan',
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // La migración no debe fallar por una incidencia secundaria de bitácora.
        }
    }
}
