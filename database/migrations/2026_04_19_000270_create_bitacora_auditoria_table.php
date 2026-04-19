<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_auditoria', function (Blueprint $table) {
            $table->id();
            $table->string('numero_activo', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modulo', 80);
            $table->string('accion', 40); // ALTA, EDICION, ELIMINACION, CARGA, DESCARGA, CAMBIO_UBICACION
            $table->string('tabla_afectada', 80)->nullable();
            $table->string('registro_clave', 80)->nullable();
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('fecha_evento');
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->nullOnDelete();
            $table->index(['modulo', 'accion']);
            $table->index(['numero_activo', 'fecha_evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_auditoria');
    }
};
