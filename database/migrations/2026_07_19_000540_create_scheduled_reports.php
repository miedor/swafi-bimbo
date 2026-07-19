<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reportes_programados')) {
            Schema::create('reportes_programados', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reporte_guardado_id')
                    ->constrained('reportes_guardados')
                    ->restrictOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->string('frecuencia', 20);
                $table->unsignedTinyInteger('dia_semana')->nullable();
                $table->unsignedTinyInteger('dia_mes')->nullable();
                $table->time('hora_local');
                $table->string('zona_horaria', 64)->default('America/Mexico_City');
                $table->string('formato', 10)->default('xlsx');
                $table->json('destinatarios');
                $table->boolean('activo')->default(true);
                $table->timestamp('proxima_ejecucion_at')->nullable();
                $table->timestamp('ultima_ejecucion_at')->nullable();
                $table->string('ultimo_estado', 20)->nullable();
                $table->string('ultimo_error_referencia', 64)->nullable();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('delete_reason', 500)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->unique(
                    'reporte_guardado_id',
                    'reportes_programados_reporte_unique'
                );
                $table->index(
                    ['activo', 'proxima_ejecucion_at'],
                    'reportes_programados_pendientes_index'
                );
                $table->index(
                    ['user_id', 'updated_at'],
                    'reportes_programados_usuario_fecha_index'
                );
            });
        }

        if (!Schema::hasTable('reportes_programados_ejecuciones')) {
            Schema::create('reportes_programados_ejecuciones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reporte_programado_id')
                    ->constrained('reportes_programados')
                    ->restrictOnDelete();
                $table->timestamp('scheduled_for');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('estado', 20)->default('encolado');
                $table->string('formato', 10);
                $table->unsignedInteger('total_registros')->nullable();
                $table->unsignedTinyInteger('destinatarios_total')->default(0);
                $table->json('destinatarios_enviados')->nullable();
                $table->string('archivo_nombre', 180)->nullable();
                $table->char('archivo_sha256', 64)->nullable();
                $table->char('error_referencia', 64)->nullable();
                $table->timestamps();

                $table->unique(
                    ['reporte_programado_id', 'scheduled_for'],
                    'reportes_programados_ejecucion_unique'
                );
                $table->index(
                    ['estado', 'scheduled_for'],
                    'reportes_programados_ejecucion_estado_index'
                );
            });
        }

        $this->registerPermission();
        $this->registerMigrationAudit();
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_programados_ejecuciones');
        Schema::dropIfExists('reportes_programados');

        if (
            Schema::hasTable('permissions') &&
            Schema::hasTable('permission_role')
        ) {
            $permissionId = DB::table('permissions')
                ->where('clave', 'reportes.programar')
                ->value('id');

            if ($permissionId) {
                DB::table('permission_role')
                    ->where('permission_id', $permissionId)
                    ->delete();

                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->delete();
            }
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', 'HABILITA_REPORTES_PROGRAMADOS')
                ->where('registro_clave', 'HU-082')
                ->delete();
        }
    }

    private function registerPermission(): void
    {
        if (
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('roles') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();
        $permissionValues = [
            'descripcion' => 'Programar la generación y entrega periódica de reportes guardados.',
            'updated_at' => $now,
            'created_at' => $now,
        ];

        if (Schema::hasColumn('permissions', 'activo')) {
            $permissionValues['activo'] = true;
        }

        DB::table('permissions')->updateOrInsert(
            ['clave' => 'reportes.programar'],
            $permissionValues
        );

        $permissionId = DB::table('permissions')
            ->where('clave', 'reportes.programar')
            ->value('id');

        if (!$permissionId) {
            return;
        }

        foreach (['Administrador SWAFI', 'Usuario Consulta / Auditoría'] as $roleName) {
            $roleId = DB::table('roles')
                ->where('nombre', $roleName)
                ->value('id');

            if (!$roleId) {
                continue;
            }

            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function registerMigrationAudit(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => 'HABILITA_REPORTES_PROGRAMADOS',
                'registro_clave' => 'HU-082',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M03 Consultas, reportes y seguimiento',
                'tabla_afectada' => 'reportes_programados',
                'antes' => null,
                'despues' => json_encode([
                    'historia_usuario' => 'HU-082',
                    'frecuencias' => ['diaria', 'semanal', 'mensual'],
                    'formatos' => ['csv', 'xlsx', 'pdf'],
                    'permiso' => 'reportes.programar',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
