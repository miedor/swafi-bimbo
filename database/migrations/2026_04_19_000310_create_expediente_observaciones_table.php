<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->string('numero_activo', 30);
            $table->string('tipo_observacion', 40);
            $table->string('prioridad', 20)->default('media');
            $table->string('estatus', 20)->default('abierta');
            $table->text('descripcion');
            $table->text('respuesta')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
            $table->index(['expediente_id', 'estatus']);
            $table->index(['numero_activo', 'estatus']);
            $table->index(['tipo_observacion', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_observaciones');
    }
};
