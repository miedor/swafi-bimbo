<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_masivas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 24)->default('previsualizada');

            $table->string('csv_nombre_original', 255);
            $table->string('csv_storage_disk', 40);
            $table->string('csv_ruta', 1024);
            $table->char('csv_hash_sha256', 64);

            $table->string('zip_nombre_original', 255);
            $table->string('zip_storage_disk', 40);
            $table->string('zip_ruta', 1024);
            $table->char('zip_hash_sha256', 64);

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

            $table->index(['user_id', 'estado']);
            $table->index(['estado', 'expira_at']);
        });

        Schema::create('importacion_masiva_filas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')
                ->constrained('importaciones_masivas')
                ->cascadeOnDelete();
            $table->unsignedInteger('numero_fila');
            $table->string('estatus', 16);
            $table->string('accion', 16)->nullable();
            $table->json('datos');
            $table->json('errores')->nullable();
            $table->json('advertencias')->nullable();
            $table->boolean('aplicada')->default(false);
            $table->json('resultado')->nullable();
            $table->timestamps();

            $table->unique(['importacion_id', 'numero_fila']);
            $table->index(['importacion_id', 'estatus']);
            $table->index(['importacion_id', 'aplicada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_masiva_filas');
        Schema::dropIfExists('importaciones_masivas');
    }
};
