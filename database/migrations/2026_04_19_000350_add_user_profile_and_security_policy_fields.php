<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('ultimo_ip');
            }

            if (!Schema::hasColumn('users', 'avatar_mime')) {
                $table->string('avatar_mime', 80)->nullable()->after('avatar_path');
            }

            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('avatar_mime');
            }

            if (!Schema::hasColumn('users', 'intentos_fallidos')) {
                $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('password_changed_at');
            }

            if (!Schema::hasColumn('users', 'ultimo_intento_fallido')) {
                $table->timestamp('ultimo_intento_fallido')->nullable()->after('intentos_fallidos');
            }

            if (!Schema::hasColumn('users', 'bloqueado_en')) {
                $table->timestamp('bloqueado_en')->nullable()->after('ultimo_intento_fallido');
            }

            if (!Schema::hasColumn('users', 'bloqueado_motivo')) {
                $table->string('bloqueado_motivo', 255)->nullable()->after('bloqueado_en');
            }
        });

        if (Schema::hasColumn('users', 'estatus')) {
            DB::table('users')
                ->whereNull('estatus')
                ->update(['estatus' => 'activo']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'bloqueado_motivo',
                'bloqueado_en',
                'ultimo_intento_fallido',
                'intentos_fallidos',
                'password_changed_at',
                'avatar_mime',
                'avatar_path',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
