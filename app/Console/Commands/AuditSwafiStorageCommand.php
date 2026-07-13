<?php

namespace App\Console\Commands;

use App\Services\SwafiStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditSwafiStorageCommand extends Command
{
    protected $signature = 'swafi:storage-audit
        {--type=all : all, avatars, documents o evidences}
        {--repair-metadata : Busca una copia íntegra en los discos configurados y corrige la metadata}
        {--disk=* : Discos adicionales que deben revisarse}
        {--quiet-success : Solo muestra incidencias y resumen}';

    protected $description = 'Audita existencia, disco e integridad SHA-256 de los archivos de SWAFI.';

    public function handle(SwafiStorageService $storage): int
    {
        $type = strtolower(trim((string) $this->option('type')));

        if (!in_array($type, ['all', 'avatars', 'documents', 'evidences'], true)) {
            $this->error('El tipo debe ser all, avatars, documents o evidences.');
            return self::FAILURE;
        }

        $disks = array_values(array_unique(array_filter([
            $storage->defaultDisk(),
            $storage->legacyDisk(),
            ...array_map(fn ($disk) => trim((string) $disk), (array) $this->option('disk')),
        ])));

        $summary = [
            'revisados' => 0,
            'correctos' => 0,
            'reparados' => 0,
            'faltantes' => 0,
            'integridad_fallida' => 0,
            'errores' => 0,
        ];

        if (in_array($type, ['all', 'avatars'], true)) {
            $this->auditAvatars($storage, $disks, $summary);
        }

        if (in_array($type, ['all', 'documents'], true)) {
            $this->auditDocuments($storage, $disks, $summary);
        }

        if (in_array($type, ['all', 'evidences'], true)) {
            $this->auditEvidences($storage, $disks, $summary);
        }

        $this->newLine();
        $this->table(
            ['Revisados', 'Correctos', 'Reparados', 'Faltantes', 'Hash inválido', 'Errores'],
            [[
                $summary['revisados'],
                $summary['correctos'],
                $summary['reparados'],
                $summary['faltantes'],
                $summary['integridad_fallida'],
                $summary['errores'],
            ]]
        );

        $this->registerAudit($summary);

        return ($summary['faltantes'] + $summary['integridad_fallida'] + $summary['errores']) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function auditAvatars(SwafiStorageService $storage, array $disks, array &$summary): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'avatar_disk')) {
            return;
        }

        foreach (DB::table('users')->whereNotNull('avatar_path')->where('avatar_path', '<>', '')->orderBy('id')->cursor() as $row) {
            $this->auditRecord(
                storage: $storage,
                label: "avatar usuario {$row->id}",
                table: 'users',
                id: (int) $row->id,
                diskColumn: 'avatar_disk',
                currentDisk: $row->avatar_disk ?: $storage->legacyDisk(),
                path: (string) $row->avatar_path,
                expectedHash: null,
                candidateDisks: $disks,
                summary: $summary
            );
        }
    }

    private function auditDocuments(SwafiStorageService $storage, array $disks, array &$summary): void
    {
        if (!Schema::hasTable('documentos_expediente') || !Schema::hasColumn('documentos_expediente', 'storage_disk')) {
            return;
        }

        foreach (DB::table('documentos_expediente')->whereNotNull('ruta_archivo')->where('ruta_archivo', '<>', '')->orderBy('id')->cursor() as $row) {
            $this->auditRecord(
                storage: $storage,
                label: "documento {$row->id}",
                table: 'documentos_expediente',
                id: (int) $row->id,
                diskColumn: 'storage_disk',
                currentDisk: $row->storage_disk ?: $storage->legacyDisk(),
                path: (string) $row->ruta_archivo,
                expectedHash: $row->hash_sha256 ?: null,
                candidateDisks: $disks,
                summary: $summary
            );
        }
    }

    private function auditEvidences(SwafiStorageService $storage, array $disks, array &$summary): void
    {
        if (!Schema::hasTable('inventario_evidencias') || !Schema::hasColumn('inventario_evidencias', 'storage_disk')) {
            return;
        }

        foreach (DB::table('inventario_evidencias')->whereNotNull('ruta_archivo')->where('ruta_archivo', '<>', '')->orderBy('id')->cursor() as $row) {
            $this->auditRecord(
                storage: $storage,
                label: "evidencia {$row->id}",
                table: 'inventario_evidencias',
                id: (int) $row->id,
                diskColumn: 'storage_disk',
                currentDisk: $row->storage_disk ?: $storage->legacyDisk(),
                path: (string) $row->ruta_archivo,
                expectedHash: $row->hash_sha256 ?: null,
                candidateDisks: $disks,
                summary: $summary
            );
        }
    }

    private function auditRecord(
        SwafiStorageService $storage,
        string $label,
        string $table,
        int $id,
        string $diskColumn,
        string $currentDisk,
        string $path,
        ?string $expectedHash,
        array $candidateDisks,
        array &$summary
    ): void {
        $summary['revisados']++;

        try {
            $validation = $storage->validate($currentDisk, $path, $expectedHash);

            if ($validation['ok']) {
                $summary['correctos']++;

                if (!$this->option('quiet-success')) {
                    $this->line("[OK] {$label} · {$currentDisk} · {$path}");
                }

                return;
            }

            if ((bool) $this->option('repair-metadata')) {
                foreach ($candidateDisks as $candidateDisk) {
                    if ($candidateDisk === $currentDisk) {
                        continue;
                    }

                    $candidate = $storage->validate($candidateDisk, $path, $expectedHash);

                    if ($candidate['ok']) {
                        DB::table($table)->where('id', $id)->update([
                            $diskColumn => $candidateDisk,
                            'updated_at' => now(),
                        ]);

                        $summary['reparados']++;
                        $this->warn("[REPARADO] {$label}: metadata {$currentDisk} → {$candidateDisk}");
                        return;
                    }
                }
            }

            if (str_contains(strtolower((string) $validation['message']), 'sha-256')) {
                $summary['integridad_fallida']++;
            } else {
                $summary['faltantes']++;
            }

            $this->error("[INCIDENCIA] {$label}: {$validation['message']}");
        } catch (\Throwable $exception) {
            $summary['errores']++;
            $this->error("[ERROR] {$label}: {$exception->getMessage()}");
        }
    }

    private function registerAudit(array $summary): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => 'AUDITORIA_STORAGE',
                'tabla_afectada' => null,
                'registro_clave' => null,
                'antes' => null,
                'despues' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'ip' => 'artisan',
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // La auditoría de archivos no debe fallar por la bitácora.
        }
    }
}
