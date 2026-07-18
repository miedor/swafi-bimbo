<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora_auditoria', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'fecha_evento'],
                'bitacora_user_fecha_index'
            );
            $table->index(
                ['fecha_evento', 'id'],
                'bitacora_fecha_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_auditoria', function (Blueprint $table): void {
            $table->dropIndex('bitacora_user_fecha_index');
            $table->dropIndex('bitacora_fecha_id_index');
        });
    }
};
