<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planta_id')->constrained('plantas')->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('edificio', 100)->nullable();
            $table->string('piso', 50)->nullable();
            $table->string('pasillo', 50)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('codigo_interno', 60)->nullable()->unique();
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};
