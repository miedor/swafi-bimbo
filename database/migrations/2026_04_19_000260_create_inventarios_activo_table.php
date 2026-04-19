<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios_activo', function (Blueprint $table) {
            $table->id();
            $table->string('numero_activo', 30);
            $table->date('fecha_inventario');
            $table->string('estatus_localizacion', 20)->default('localizado');
            $table->foreignId('ubicacion_verificada_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
            $table->index(['fecha_inventario', 'estatus_localizacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios_activo');
    }
};
