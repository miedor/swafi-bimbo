<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('valores_activo')) {
            return;
        }

        $duplicados = DB::table('valores_activo')
            ->select('numero_activo', DB::raw('COUNT(*) as total'))
            ->groupBy('numero_activo')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicados as $duplicado) {
            $registros = DB::table('valores_activo')
                ->where('numero_activo', $duplicado->numero_activo)
                ->orderByRaw("CASE WHEN estatus_contable = 'baja' OR (COALESCE(valor_fiscal, 0) > 0 AND COALESCE(valor_financiero, 0) > 0) THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $registroConservado = $registros->first();

            if (!$registroConservado) {
                continue;
            }

            $idsEliminar = $registros
                ->where('id', '<>', $registroConservado->id)
                ->pluck('id')
                ->all();

            if (!empty($idsEliminar)) {
                DB::table('valores_activo')
                    ->whereIn('id', $idsEliminar)
                    ->delete();
            }
        }

        if (!$this->indexExists('valores_activo', 'valores_activo_numero_activo_unique')) {
            Schema::table('valores_activo', function (Blueprint $table) {
                $table->unique('numero_activo', 'valores_activo_numero_activo_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('valores_activo')) {
            return;
        }

        if ($this->indexExists('valores_activo', 'valores_activo_numero_activo_unique')) {
            Schema::table('valores_activo', function (Blueprint $table) {
                $table->dropUnique('valores_activo_numero_activo_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::select(
            'SELECT COUNT(1) as total
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result[0]->total ?? 0) > 0;
    }
};
