<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {

            $table->id();
            $table->string('clave', 30)->unique();
            $table->string('descripcion', 150);
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
    }
};
