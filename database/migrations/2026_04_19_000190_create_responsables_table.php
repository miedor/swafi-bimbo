<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsables', function (Blueprint $table) {

            $table->id();
            $table->string('nombre', 120);
            $table->string('correo', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsables');
    }
};
