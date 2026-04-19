<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantas', function (Blueprint $table) {

            $table->id();
            $table->string('clave', 30)->unique();
            $table->string('nombre', 150);
            $table->string('estado', 100)->nullable();
            $table->string('pais', 80)->default('México');
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantas');
    }
};
