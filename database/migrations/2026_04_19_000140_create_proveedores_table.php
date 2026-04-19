<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {

            $table->id();
            $table->string('rfc', 13)->unique();
            $table->string('nombre', 150);
            $table->string('correo', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('estatus', 20)->default('activo');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
