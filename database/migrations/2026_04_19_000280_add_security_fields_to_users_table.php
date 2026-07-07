<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'usuario')) {
                $table->string('usuario', 80)->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('users', 'estatus')) {
                $table->string('estatus', 20)->default('activo')->after('password');
            }

            if (!Schema::hasColumn('users', 'ultimo_acceso')) {
                $table->timestamp('ultimo_acceso')->nullable()->after('remember_token');
            }

            if (!Schema::hasColumn('users', 'ultimo_ip')) {
                $table->string('ultimo_ip', 45)->nullable()->after('ultimo_acceso');
            }
        });

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'usuario')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            if (!empty($user->usuario)) {
                continue;
            }

            $base = Str::of($user->email ?: $user->name ?: 'usuario')
                ->before('@')
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9.]+/', '.')
                ->trim('.')
                ->value();

            $base = $base !== '' ? $base : 'usuario';
            $candidate = $base;
            $counter = 1;

            while (
                DB::table('users')
                    ->where('usuario', $candidate)
                    ->where('id', '<>', $user->id)
                    ->exists()
            ) {
                $candidate = $base . '.' . $counter;
                $counter++;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'usuario' => $candidate,
                    'estatus' => 'activo',
                    'updated_at' => now(),
                ]);
        }

        DB::table('users')
            ->where('email', 'admin.swafi@bimbo.local')
            ->update([
                'usuario' => 'admin.swafi',
                'estatus' => 'activo',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ultimo_ip')) {
                $table->dropColumn('ultimo_ip');
            }

            if (Schema::hasColumn('users', 'ultimo_acceso')) {
                $table->dropColumn('ultimo_acceso');
            }

            if (Schema::hasColumn('users', 'estatus')) {
                $table->dropColumn('estatus');
            }

            if (Schema::hasColumn('users', 'usuario')) {
                $table->dropColumn('usuario');
            }
        });
    }
};
