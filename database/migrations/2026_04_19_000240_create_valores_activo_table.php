<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_activo', function (Blueprint $table) {
            $table->id();
            $table->string('numero_activo', 30);
            $table->decimal('valor_fiscal', 18, 2)->nullable();
            $table->decimal('valor_financiero', 18, 2)->nullable();
            $table->decimal('depreciacion_acumulada', 18, 2)->default(0);
            $table->decimal('valor_en_libros', 18, 2)->nullable();
            $table->unsignedSmallInteger('vida_util_meses')->nullable();
            $table->string('estatus_contable', 30)->default('vigente');
            $table->date('fecha_corte')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
            $table->index(['numero_activo', 'fecha_corte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_activo');
    }
};
