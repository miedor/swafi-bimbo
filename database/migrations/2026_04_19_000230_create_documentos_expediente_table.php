<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_expediente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->string('tipo_documento', 30); // PDF, XML, EVIDENCIA, NOTA, MANUAL
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('vigente')->default(true);
            $table->foreignId('cargado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expediente_id', 'tipo_documento']);
            $table->index(['hash_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_expediente');
    }
};
