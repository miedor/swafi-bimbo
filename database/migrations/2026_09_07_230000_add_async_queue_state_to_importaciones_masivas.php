<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_masivas', function (Blueprint $table): void {
            $table->string('procesamiento_estado', 24)->nullable()->after('expira_at');
            $table->timestamp('procesamiento_solicitado_at')->nullable()->after('procesamiento_estado');
            $table->string('procesamiento_error_referencia', 64)->nullable()->after('procesamiento_porcentaje');

            $table->index('procesamiento_estado', 'imp_masivas_proc_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_masivas', function (Blueprint $table): void {
            $table->dropIndex('imp_masivas_proc_estado_idx');
            $table->dropColumn([
                'procesamiento_estado',
                'procesamiento_solicitado_at',
                'procesamiento_error_referencia',
            ]);
        });
    }
};
