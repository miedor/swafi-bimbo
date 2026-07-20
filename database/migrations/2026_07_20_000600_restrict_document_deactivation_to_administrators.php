<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'documentos.eliminar';

    private const ADMIN_ROLE = 'Administrador SWAFI';

    private const AUDIT_ACTION = 'HABILITA_BAJA_DOCUMENTO_ADMIN';

    public function up(): void
    {
        if (Schema::hasTable('documentos_expediente')) {
            Schema::table('documentos_expediente', function (Blueprint $table): void {
                if (!Schema::hasColumn('documentos_expediente', 'motivo_baja')) {
                    $table->string('motivo_baja', 500)
                        ->nullable()
                        ->after('vigente');
                }

                if (!Schema::hasColumn('documentos_expediente', 'dado_baja_at')) {
                    $table->timestamp('dado_baja_at')
                        ->nullable()
                        ->after('motivo_baja');
                }

                if (!Schema::hasColumn('documentos_expediente', 'dado_baja_por')) {
                    $table->unsignedBigInteger('dado_baja_por')
                        ->nullable()
                        ->after('dado_baja_at');
                }
            });

            if (
                Schema::hasColumn('documentos_expediente', 'dado_baja_por')
                && Schema::hasTable('users')
            ) {
                Schema::table('documentos_expediente', function (Blueprint $table): void {
                    $table->foreign('dado_baja_por', 'doc_exp_baja_usuario_fk')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                });
            }

            Schema::table('documentos_expediente', function (Blueprint $table): void {
                $table->index(
                    ['expediente_id', 'vigente', 'dado_baja_at'],
                    'doc_exp_vigente_baja_idx'
                );
            });
        }

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            DB::transaction(function (): void {
                $this->createOrUpdatePermission();
                $this->restrictPermissionToAdministrator();
                $this->registerAuditEvent();
            }, 3);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-015')
                ->delete();
        }

        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('permission_role')
        ) {
            $permissionId = DB::table('permissions')
                ->where('clave', self::PERMISSION)
                ->value('id');

            if ($permissionId) {
                DB::table('permission_role')
                    ->where('permission_id', $permissionId)
                    ->delete();

                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->delete();
            }
        }

        if (!Schema::hasTable('documentos_expediente')) {
            return;
        }

        if (Schema::hasColumn('documentos_expediente', 'dado_baja_por')) {
            Schema::table('documentos_expediente', function (Blueprint $table): void {
                $table->dropForeign('doc_exp_baja_usuario_fk');
            });
        }

        Schema::table('documentos_expediente', function (Blueprint $table): void {
            $table->dropIndex('doc_exp_vigente_baja_idx');
        });

        Schema::table('documentos_expediente', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('documentos_expediente', 'motivo_baja') ? 'motivo_baja' : null,
                Schema::hasColumn('documentos_expediente', 'dado_baja_at') ? 'dado_baja_at' : null,
                Schema::hasColumn('documentos_expediente', 'dado_baja_por') ? 'dado_baja_por' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function createOrUpdatePermission(): void
    {
        $now = now();
        $payload = [
            'descripcion' => 'Dar de baja lógicamente documentos del expediente. Permiso exclusivo del Administrador SWAFI.',
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('permissions', 'activo')) {
            $payload['activo'] = 1;
        }

        if (Schema::hasColumn('permissions', 'es_sistema')) {
            $payload['es_sistema'] = 1;
        }

        if (!DB::table('permissions')->where('clave', self::PERMISSION)->exists()) {
            $payload['created_at'] = $now;
        }

        DB::table('permissions')->updateOrInsert(
            ['clave' => self::PERMISSION],
            $payload
        );
    }

    private function restrictPermissionToAdministrator(): void
    {
        $permissionId = DB::table('permissions')
            ->where('clave', self::PERMISSION)
            ->value('id');
        $administratorRoleId = DB::table('roles')
            ->where('nombre', self::ADMIN_ROLE)
            ->value('id');

        if (!$permissionId) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->when(
                $administratorRoleId,
                fn ($query) => $query->where('role_id', '<>', $administratorRoleId)
            )
            ->delete();

        if ($administratorRoleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $administratorRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function registerAuditEvent(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => self::AUDIT_ACTION,
                'registro_clave' => 'HU-015',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M01 Gestión de expedientes de activo fijo',
                'tabla_afectada' => 'documentos_expediente,permissions,permission_role',
                'antes' => json_encode([
                    'permiso_reutilizado' => 'documentos.cargar',
                    'motivo_baja_en_registro' => false,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'despues' => json_encode([
                    'historia_usuario' => 'HU-015',
                    'permiso_exclusivo' => self::PERMISSION,
                    'rol_autorizado' => self::ADMIN_ROLE,
                    'campos_trazabilidad' => [
                        'motivo_baja',
                        'dado_baja_at',
                        'dado_baja_por',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
