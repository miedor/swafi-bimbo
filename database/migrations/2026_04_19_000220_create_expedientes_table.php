<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_activo', 30);
            $table->string('folio_factura', 80);
            $table->string('uuid_cfdi', 50)->nullable()->unique();
            $table->date('fecha_factura');
            $table->decimal('monto_factura', 18, 2);
            $table->string('moneda', 10)->default('MXN');
            $table->string('estatus', 20)->default('incompleto');
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('numero_activo')->references('numero_activo')->on('activos')->cascadeOnDelete();
            $table->unique(['numero_activo', 'folio_factura']);
            $table->index(['fecha_factura', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
