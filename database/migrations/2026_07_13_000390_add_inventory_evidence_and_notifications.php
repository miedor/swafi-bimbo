<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventarios_activo')) {
            $hasNotificarA = Schema::hasColumn('inventarios_activo', 'notificar_a');
            $hasNotificadoAt = Schema::hasColumn('inventarios_activo', 'notificado_at');
            $hasNotificacionError = Schema::hasColumn('inventarios_activo', 'notificacion_error');
            $hasRequiereAtencion = Schema::hasColumn('inventarios_activo', 'requiere_atencion');

            Schema::table('inventarios_activo', function (Blueprint $table) use (
                $hasNotificarA,
                $hasNotificadoAt,
                $hasNotificacionError,
                $hasRequiereAtencion
            ) {
                if (!$hasNotificarA) {
                    $table->foreignId('notificar_a')
                        ->nullable()
                        ->after('verificado_por')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!$hasNotificadoAt) {
                    $table->timestamp('notificado_at')->nullable()->after('notificar_a');
                }

                if (!$hasNotificacionError) {
                    $table->text('notificacion_error')->nullable()->after('notificado_at');
                }

                if (!$hasRequiereAtencion) {
                    $table->boolean('requiere_atencion')->default(false)->after('notificacion_error');
                }
            });
        }

        if (!Schema::hasTable('inventario_evidencias')) {
            Schema::create('inventario_evidencias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventario_id')
                    ->constrained('inventarios_activo')
                    ->cascadeOnDelete();
                $table->string('numero_activo', 30);
                $table->string('tipo_evidencia', 20)->default('DOCUMENTO');
                $table->string('nombre_archivo', 255);
                $table->string('ruta_archivo', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('tamano_bytes')->nullable();
                $table->char('hash_sha256', 64)->nullable();
                $table->boolean('vigente')->default(true);
                $table->foreignId('cargado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('numero_activo')
                    ->references('numero_activo')
                    ->on('activos')
                    ->cascadeOnDelete();

                $table->index(['inventario_id', 'vigente']);
                $table->index(['numero_activo', 'vigente']);
                $table->index('hash_sha256');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_evidencias');

        if (!Schema::hasTable('inventarios_activo')) {
            return;
        }

        $dropNotificarA = Schema::hasColumn('inventarios_activo', 'notificar_a');
        $columns = array_values(array_filter([
            Schema::hasColumn('inventarios_activo', 'notificado_at') ? 'notificado_at' : null,
            Schema::hasColumn('inventarios_activo', 'notificacion_error') ? 'notificacion_error' : null,
            Schema::hasColumn('inventarios_activo', 'requiere_atencion') ? 'requiere_atencion' : null,
        ]));

        Schema::table('inventarios_activo', function (Blueprint $table) use ($dropNotificarA, $columns) {
            if ($dropNotificarA) {
                $table->dropConstrainedForeignId('notificar_a');
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
