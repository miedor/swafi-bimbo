<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reportes_guardados')) {
            Schema::create('reportes_guardados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('nombre', 120);
                $table->string('tipo_reporte', 60);
                $table->json('filtros')->nullable();
                $table->json('columnas')->nullable();
                $table->string('orientacion', 20)->default('horizontal');
                $table->timestamps();

                $table->unique(
                    ['user_id', 'nombre'],
                    'reportes_guardados_usuario_nombre_unique'
                );

                $table->index(
                    ['user_id', 'tipo_reporte', 'updated_at'],
                    'reportes_guardados_usuario_tipo_fecha_index'
                );
            });
        }

        if (
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('roles') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();

        $permissions = [
            'reportes.documentales' => 'Consultar reportes documentales y expedientes pendientes.',
            'reportes.valores' => 'Consultar reportes fiscales y financieros.',
            'reportes.inventario' => 'Consultar reportes de ubicación, inventario y discrepancias.',
            'reportes.bitacora' => 'Consultar reportes de actividad y bitácora.',
            'reportes.exportar_excel' => 'Exportar reportes autorizados a Excel.',
            'reportes.exportar_pdf' => 'Exportar reportes autorizados a PDF.',
            'reportes.plantillas' => 'Guardar y administrar parámetros o plantillas personales de reporte.',
        ];

        foreach ($permissions as $clave => $descripcion) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $clave],
                [
                    'descripcion' => $descripcion,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $rolePermissions = [
            'Administrador SWAFI' => array_keys($permissions),

            // El perfil de Captura conserva su alcance operativo actual y no recibe
            // permisos de reportes, evitando ampliar privilegios sin autorización.

            'Usuario Consulta / Auditoría' => [
                'reportes.exportar',
                'reportes.documentales',
                'reportes.valores',
                'reportes.inventario',
                'reportes.bitacora',
                'reportes.exportar_excel',
                'reportes.exportar_pdf',
                'reportes.plantillas',
            ],

            'Usuario Planta / Inventarios' => [
                'reportes.exportar',
                'reportes.inventario',
                'reportes.exportar_excel',
                'reportes.exportar_pdf',
                'reportes.plantillas',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionKeys) {
            $roleId = DB::table('roles')
                ->where('nombre', $roleName)
                ->value('id');

            if (!$roleId) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                $permissionId = DB::table('permissions')
                    ->where('clave', $permissionKey)
                    ->value('id');

                if (!$permissionId) {
                    continue;
                }

                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reportes_guardados')) {
            Schema::dropIfExists('reportes_guardados');
        }

        if (
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $permissionKeys = [
            'reportes.documentales',
            'reportes.valores',
            'reportes.inventario',
            'reportes.bitacora',
            'reportes.exportar_excel',
            'reportes.exportar_pdf',
            'reportes.plantillas',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('clave', $permissionKeys)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }
};
