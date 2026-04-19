<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {

            $table->id();
            $table->foreignId('planta_id')->constrained('plantas')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();
            $table->unique(['planta_id', 'nombre']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
