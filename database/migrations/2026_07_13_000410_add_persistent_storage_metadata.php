<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'avatar_disk')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('avatar_disk', 40)
                    ->nullable()
                    ->after('avatar_path');
            });
        }

        if (Schema::hasTable('documentos_expediente') && !Schema::hasColumn('documentos_expediente', 'storage_disk')) {
            Schema::table('documentos_expediente', function (Blueprint $table): void {
                $table->string('storage_disk', 40)
                    ->nullable()
                    ->after('ruta_archivo');

                $table->index('storage_disk', 'documentos_expediente_storage_disk_idx');
            });
        }

        if (Schema::hasTable('inventario_evidencias') && !Schema::hasColumn('inventario_evidencias', 'storage_disk')) {
            Schema::table('inventario_evidencias', function (Blueprint $table): void {
                $table->string('storage_disk', 40)
                    ->nullable()
                    ->after('ruta_archivo');

                $table->index('storage_disk', 'inventario_evidencias_storage_disk_idx');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar_disk')) {
            DB::table('users')
                ->whereNotNull('avatar_path')
                ->where('avatar_path', '<>', '')
                ->whereNull('avatar_disk')
                ->update(['avatar_disk' => 'local']);
        }

        if (Schema::hasTable('documentos_expediente') && Schema::hasColumn('documentos_expediente', 'storage_disk')) {
            DB::table('documentos_expediente')
                ->whereNotNull('ruta_archivo')
                ->where('ruta_archivo', '<>', '')
                ->whereNull('storage_disk')
                ->update(['storage_disk' => 'local']);
        }

        if (Schema::hasTable('inventario_evidencias') && Schema::hasColumn('inventario_evidencias', 'storage_disk')) {
            DB::table('inventario_evidencias')
                ->whereNotNull('ruta_archivo')
                ->where('ruta_archivo', '<>', '')
                ->whereNull('storage_disk')
                ->update(['storage_disk' => 'local']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_evidencias') && Schema::hasColumn('inventario_evidencias', 'storage_disk')) {
            Schema::table('inventario_evidencias', function (Blueprint $table): void {
                $table->dropIndex('inventario_evidencias_storage_disk_idx');
                $table->dropColumn('storage_disk');
            });
        }

        if (Schema::hasTable('documentos_expediente') && Schema::hasColumn('documentos_expediente', 'storage_disk')) {
            Schema::table('documentos_expediente', function (Blueprint $table): void {
                $table->dropIndex('documentos_expediente_storage_disk_idx');
                $table->dropColumn('storage_disk');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar_disk')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_disk');
            });
        }
    }
};
