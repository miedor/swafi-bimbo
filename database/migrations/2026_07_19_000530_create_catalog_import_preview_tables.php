<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_catalogo', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catalogo', 40);
            $table->string('estado', 24)->default('previsualizada');

            $table->string('archivo_nombre_original', 255);
            $table->string('archivo_extension', 10);
            $table->char('archivo_hash_sha256', 64);

            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_aceptadas')->default(0);
            $table->unsignedInteger('filas_observadas')->default(0);
            $table->unsignedInteger('filas_rechazadas')->default(0);
            $table->unsignedInteger('filas_insertadas')->default(0);
            $table->unsignedInteger('filas_actualizadas')->default(0);

            $table->json('resumen')->nullable();
            $table->timestamp('aplicada_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamp('expira_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado'], 'import_cat_usuario_estado_idx');
            $table->index(['catalogo', 'created_at'], 'import_cat_catalogo_fecha_idx');
            $table->index(['estado', 'expira_at'], 'import_cat_estado_expira_idx');
        });

        Schema::create('importacion_catalogo_filas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')
                ->constrained('importaciones_catalogo')
                ->restrictOnDelete();
            $table->unsignedInteger('numero_fila');
            $table->string('estatus', 16);
            $table->string('accion', 16)->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('datos');
            $table->json('errores')->nullable();
            $table->json('advertencias')->nullable();
            $table->boolean('aplicada')->default(false);
            $table->json('resultado')->nullable();
            $table->timestamps();

            $table->unique(['importacion_id', 'numero_fila'], 'import_cat_fila_unica');
            $table->index(['importacion_id', 'estatus'], 'import_cat_fila_estatus_idx');
            $table->index(['importacion_id', 'aplicada'], 'import_cat_fila_aplicada_idx');
            $table->index(['importacion_id', 'accion'], 'import_cat_fila_accion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_catalogo_filas');
        Schema::dropIfExists('importaciones_catalogo');
    }
};
