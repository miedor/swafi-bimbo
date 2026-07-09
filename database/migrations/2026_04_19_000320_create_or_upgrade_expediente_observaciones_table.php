<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expediente_observaciones')) {
            Schema::create('expediente_observaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
                $table->string('numero_activo', 30);
                $table->string('tipo_observacion', 50);
                $table->string('prioridad', 20)->default('media');
                $table->string('estatus', 20)->default('abierta');
                $table->text('descripcion');
                $table->text('respuesta_atencion')->nullable();
                $table->text('comentario_validacion')->nullable();
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('fecha_atencion')->nullable();
                $table->timestamp('fecha_validacion')->nullable();
                $table->timestamp('fecha_cancelacion')->nullable();
                $table->timestamps();

                $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
                $table->index(['expediente_id', 'estatus']);
                $table->index(['numero_activo', 'estatus']);
                $table->index(['tipo_observacion', 'prioridad']);
            });

            return;
        }

        Schema::table('expediente_observaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('expediente_observaciones', 'respuesta_atencion')) {
                $table->text('respuesta_atencion')->nullable()->after('descripcion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'comentario_validacion')) {
                $table->text('comentario_validacion')->nullable()->after('respuesta_atencion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'atendido_por')) {
                $table->foreignId('atendido_por')->nullable()->after('creado_por')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('expediente_observaciones', 'validado_por')) {
                $table->foreignId('validado_por')->nullable()->after('atendido_por')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('expediente_observaciones', 'cancelado_por')) {
                $table->foreignId('cancelado_por')->nullable()->after('validado_por')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('expediente_observaciones', 'fecha_atencion')) {
                $table->timestamp('fecha_atencion')->nullable()->after('actualizado_por');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'fecha_validacion')) {
                $table->timestamp('fecha_validacion')->nullable()->after('fecha_atencion');
            }

            if (!Schema::hasColumn('expediente_observaciones', 'fecha_cancelacion')) {
                $table->timestamp('fecha_cancelacion')->nullable()->after('fecha_validacion');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_observaciones');
    }
};
