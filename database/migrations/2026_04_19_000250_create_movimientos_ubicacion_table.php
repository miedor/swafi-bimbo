<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_ubicacion', function (Blueprint $table) {
            $table->id();
            $table->string('numero_activo', 30);
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->string('motivo', 120)->nullable();
            $table->text('evidencia')->nullable();
            $table->timestamp('fecha_movimiento');
            $table->foreignId('responsable_id')->nullable()->constrained('responsables')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
            $table->index(['numero_activo', 'fecha_movimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_ubicacion');
    }
};
