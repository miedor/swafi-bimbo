<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AUDIT_ACTION = 'HABILITA_LAYOUT_XLSX_MASIVO';

    public function up(): void
    {
        if (!Schema::hasTable('importaciones_masivas')) {
            return;
        }

        if (!Schema::hasColumn('importaciones_masivas', 'layout_formato')) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->string('layout_formato', 8)
                    ->default('csv')
                    ->after('csv_hash_sha256');
            });
        }

        DB::table('importaciones_masivas')
            ->whereNull('layout_formato')
            ->orWhere('layout_formato', '')
            ->update(['layout_formato' => 'csv']);

        if (Schema::hasTable('bitacora_auditoria')) {
            $now = now();

            DB::table('bitacora_auditoria')->updateOrInsert(
                [
                    'accion' => self::AUDIT_ACTION,
                    'registro_clave' => 'HU-017/HU-018',
                ],
                [
                    'numero_activo' => null,
                    'user_id' => null,
                    'modulo' => 'M01 Gestión de expedientes de activo fijo',
                    'tabla_afectada' => 'importaciones_masivas',
                    'antes' => null,
                    'despues' => json_encode([
                        'layout_csv' => true,
                        'layout_xlsx' => true,
                        'previsualizacion' => true,
                        'compatibilidad_historica' => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'ip' => null,
                    'fecha_evento' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-017/HU-018')
                ->delete();
        }

        if (
            Schema::hasTable('importaciones_masivas')
            && Schema::hasColumn('importaciones_masivas', 'layout_formato')
        ) {
            Schema::table('importaciones_masivas', function (Blueprint $table): void {
                $table->dropColumn('layout_formato');
            });
        }
    }
};
