<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cfdi_validaciones')) {
            Schema::create('cfdi_validaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
                $table->foreignId('documento_id')->unique()->constrained('documentos_expediente')->cascadeOnDelete();
                $table->string('numero_activo', 30);

                $table->string('version_cfdi', 10)->nullable();
                $table->string('uuid_cfdi', 50)->nullable();
                $table->string('rfc_emisor', 13)->nullable();
                $table->string('nombre_emisor', 200)->nullable();
                $table->string('rfc_receptor', 13)->nullable();
                $table->dateTime('fecha_emision')->nullable();
                $table->decimal('subtotal', 18, 2)->nullable();
                $table->decimal('descuento', 18, 2)->nullable();
                $table->decimal('total', 18, 2)->nullable();
                $table->string('moneda', 10)->nullable();
                $table->decimal('tipo_cambio', 18, 6)->nullable();
                $table->string('tipo_comprobante', 10)->nullable();
                $table->string('metodo_pago', 20)->nullable();
                $table->string('forma_pago', 20)->nullable();
                $table->string('lugar_expedicion', 10)->nullable();

                $table->boolean('xml_bien_formado')->default(false);
                $table->boolean('sello_presente')->default(false);
                $table->boolean('certificado_presente')->default(false);
                $table->boolean('timbre_presente')->default(false);
                $table->boolean('coincide_uuid')->nullable();
                $table->boolean('coincide_rfc')->nullable();
                $table->boolean('coincide_fecha')->nullable();
                $table->boolean('coincide_monto')->nullable();
                $table->boolean('coincide_moneda')->nullable();
                $table->decimal('diferencia_monto', 18, 2)->nullable();

                $table->string('estatus_validacion', 20)->default('observado');
                $table->json('errores')->nullable();
                $table->json('advertencias')->nullable();
                $table->json('datos_extraidos')->nullable();
                $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validado_at')->nullable();
                $table->timestamps();

                $table->foreign('numero_activo')
                    ->references('numero_activo')
                    ->on('activos')
                    ->cascadeOnDelete();

                $table->index(['expediente_id', 'estatus_validacion'], 'cfdi_val_exp_estatus_idx');
                $table->index(['numero_activo', 'validado_at'], 'cfdi_val_activo_fecha_idx');
                $table->index('uuid_cfdi', 'cfdi_val_uuid_idx');
            });
        }

        if (Schema::hasTable('valores_activo')) {
            Schema::table('valores_activo', function (Blueprint $table) {
                if (!Schema::hasColumn('valores_activo', 'moneda')) {
                    $table->string('moneda', 10)->default('MXN')->after('valor_financiero');
                }

                if (!Schema::hasColumn('valores_activo', 'tipo_cambio')) {
                    $table->decimal('tipo_cambio', 18, 6)->nullable()->after('moneda');
                }

                if (!Schema::hasColumn('valores_activo', 'fecha_tipo_cambio')) {
                    $table->date('fecha_tipo_cambio')->nullable()->after('tipo_cambio');
                }

                if (!Schema::hasColumn('valores_activo', 'origen_tipo_cambio')) {
                    $table->string('origen_tipo_cambio', 120)->nullable()->after('fecha_tipo_cambio');
                }

                if (!Schema::hasColumn('valores_activo', 'motivo_cambio')) {
                    $table->text('motivo_cambio')->nullable()->after('estatus_contable');
                }

                if (!Schema::hasColumn('valores_activo', 'cfdi_validacion_id')) {
                    $table->foreignId('cfdi_validacion_id')
                        ->nullable()
                        ->after('motivo_cambio')
                        ->constrained('cfdi_validaciones')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('valores_activo', 'conciliacion_cfdi')) {
                    $table->string('conciliacion_cfdi', 20)->default('sin_xml')->after('cfdi_validacion_id');
                }

                if (!Schema::hasColumn('valores_activo', 'conciliacion_detalle')) {
                    $table->json('conciliacion_detalle')->nullable()->after('conciliacion_cfdi');
                }
            });

            DB::table('valores_activo')
                ->whereNull('moneda')
                ->orWhere('moneda', '')
                ->update(['moneda' => 'MXN']);
        }

        $this->createPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('valores_activo')) {
            Schema::table('valores_activo', function (Blueprint $table) {
                if (Schema::hasColumn('valores_activo', 'cfdi_validacion_id')) {
                    $table->dropConstrainedForeignId('cfdi_validacion_id');
                }

                $columns = [
                    'moneda',
                    'tipo_cambio',
                    'fecha_tipo_cambio',
                    'origen_tipo_cambio',
                    'motivo_cambio',
                    'conciliacion_cfdi',
                    'conciliacion_detalle',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('valores_activo', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('cfdi_validaciones');

        if (Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $ids = DB::table('permissions')
                ->whereIn('clave', ['cfdi.validar', 'valores.ver'])
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }
    }

    private function createPermissions(): void
    {
        if (
            !Schema::hasTable('permissions') ||
            !Schema::hasTable('roles') ||
            !Schema::hasTable('permission_role')
        ) {
            return;
        }

        $now = now();
        $permissions = [
            'cfdi.validar' => 'Ejecutar validación técnica del XML CFDI y conciliarlo contra el expediente.',
            'valores.ver' => 'Consultar valores fiscales y financieros en modo de solo lectura.',
        ];

        foreach ($permissions as $clave => $descripcion) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $clave],
                ['descripcion' => $descripcion, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $rolePermissions = [
            'Administrador SWAFI' => ['cfdi.validar', 'valores.ver'],
            'Usuario Captura' => ['cfdi.validar', 'valores.ver'],
            'Usuario Consulta / Auditoría' => ['cfdi.validar', 'valores.ver'],
            'Usuario Planta / Inventarios' => ['valores.ver'],
        ];

        foreach ($rolePermissions as $roleName => $permissionKeys) {
            $roleId = DB::table('roles')->where('nombre', $roleName)->value('id');

            if (!$roleId) {
                continue;
            }

            foreach ($permissionKeys as $permissionKey) {
                $permissionId = DB::table('permissions')->where('clave', $permissionKey)->value('id');

                if ($permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }
};
