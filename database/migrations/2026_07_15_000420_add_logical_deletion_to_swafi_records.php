<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'expedientes',
        'valores_activo',
        'busquedas_guardadas',
        'reportes_guardados',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $hasDeletedAt = Schema::hasColumn($tableName, 'deleted_at');
            $hasDeletedBy = Schema::hasColumn($tableName, 'deleted_by');
            $hasDeleteReason = Schema::hasColumn($tableName, 'delete_reason');

            Schema::table($tableName, function (Blueprint $table) use (
                $hasDeletedAt,
                $hasDeletedBy,
                $hasDeleteReason,
                $tableName
            ): void {
                if (!$hasDeletedAt) {
                    $table->softDeletes();
                    $table->index('deleted_at', $tableName.'_deleted_at_index');
                }

                if (!$hasDeletedBy) {
                    $table->foreignId('deleted_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!$hasDeleteReason) {
                    $table->string('delete_reason', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $hasDeletedBy = Schema::hasColumn($tableName, 'deleted_by');
            $columns = array_values(array_filter([
                Schema::hasColumn($tableName, 'delete_reason') ? 'delete_reason' : null,
                Schema::hasColumn($tableName, 'deleted_at') ? 'deleted_at' : null,
            ]));

            Schema::table($tableName, function (Blueprint $table) use ($hasDeletedBy, $columns): void {
                if ($hasDeletedBy) {
                    $table->dropConstrainedForeignId('deleted_by');
                }

                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
