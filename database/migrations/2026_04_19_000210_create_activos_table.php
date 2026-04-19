<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->string('numero_activo', 30)->primary();
            $table->foreignId('tipo_activo_id')->constrained('tipos_activo')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('centro_costo_id')->constrained('centros_costo')->restrictOnDelete();
            $table->foreignId('planta_id')->constrained('plantas')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('responsables')->nullOnDelete();

            $table->string('descripcion', 255);
            $table->string('serie', 120)->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->string('estatus_operativo', 20)->default('en_operacion');
            $table->string('estatus_documental', 20)->default('incompleto');
            $table->boolean('activo')->default(true);

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proveedor_id', 'fecha_adquisicion']);
            $table->index(['planta_id', 'centro_costo_id']);
            $table->index(['estatus_documental', 'estatus_operativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
