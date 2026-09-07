<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_masivas', function (Blueprint $table): void {
            $table->timestamp('procesamiento_iniciado_at')->nullable();
            $table->timestamp('procesamiento_finalizado_at')->nullable();
            $table->unsignedTinyInteger('procesamiento_porcentaje')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_masivas', function (Blueprint $table): void {
            $table->dropColumn([
                'procesamiento_iniciado_at',
                'procesamiento_finalizado_at',
                'procesamiento_porcentaje',
            ]);
        });
    }
};
