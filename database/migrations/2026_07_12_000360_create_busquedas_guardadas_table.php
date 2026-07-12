<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('busquedas_guardadas')) {
            return;
        }

        Schema::create('busquedas_guardadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('modulo', 30)->default('busqueda');
            $table->json('filtros');
            $table->timestamps();

            $table->unique(
                ['user_id', 'modulo', 'nombre'],
                'busquedas_guardadas_usuario_modulo_nombre_unique'
            );

            $table->index(
                ['user_id', 'modulo', 'updated_at'],
                'busquedas_guardadas_usuario_modulo_fecha_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busquedas_guardadas');
    }
};
