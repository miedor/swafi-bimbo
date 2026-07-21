<?php

namespace Tests\Unit;

use App\Services\RolePermissionManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogCaptureAdministrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedSecurityMatrix();
    }

    protected function tearDown(): void
    {
        foreach ([
            'bitacora_auditoria',
            'role_user',
            'permission_role',
            'permissions',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_capture_role_keeps_catalog_administration_when_an_update_omits_it(): void
    {
        $result = app(RolePermissionManagementService::class)->saveRole(
            validated: [
                'id' => 2,
                'nombre' => 'Usuario Captura',
                'descripcion' => 'Captura contable y administración de catálogos base.',
                'activo' => 1,
                'permission_ids' => [1],
            ],
            actorId: 99,
            ip: '127.0.0.1'
        );

        self::assertFalse($result['created']);
        self::assertTrue($result['permissions_changed']);
        self::assertSame(
            ['catalogos.administrar', 'dashboard.ver'],
            $this->permissionKeysForRole(2)
        );

        $audit = DB::table('bitacora_auditoria')->first();
        self::assertSame('SEGURIDAD_ROL_ACTUALIZACION', $audit->accion);
        self::assertStringContainsString('"permisos_actualizados":true', (string) $audit->despues);
    }

    public function test_custom_role_does_not_receive_capture_catalog_administration_implicitly(): void
    {
        app(RolePermissionManagementService::class)->saveRole(
            validated: [
                'id' => 3,
                'nombre' => 'Rol consulta limitado',
                'descripcion' => 'Rol personalizado con acceso únicamente al dashboard.',
                'activo' => 1,
                'permission_ids' => [1],
            ],
            actorId: 99,
            ip: null
        );

        self::assertSame(['dashboard.ver'], $this->permissionKeysForRole(3));
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->boolean('es_sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->boolean('es_sistema')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unique(['role_id', 'user_id']);
        });

        Schema::create('bitacora_auditoria', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('modulo');
            $table->string('accion', 40);
            $table->string('tabla_afectada')->nullable();
            $table->string('registro_clave')->nullable();
            $table->text('antes')->nullable();
            $table->text('despues')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('fecha_evento')->nullable();
            $table->timestamps();
        });
    }

    private function seedSecurityMatrix(): void
    {
        $now = now();

        DB::table('roles')->insert([
            [
                'id' => 1,
                'nombre' => 'Administrador SWAFI',
                'descripcion' => 'Administración integral.',
                'activo' => 1,
                'es_sistema' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'nombre' => 'Usuario Captura',
                'descripcion' => 'Captura de expedientes.',
                'activo' => 1,
                'es_sistema' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'nombre' => 'Rol consulta limitado',
                'descripcion' => 'Rol personalizado de prueba.',
                'activo' => 1,
                'es_sistema' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('permissions')->insert([
            [
                'id' => 1,
                'clave' => 'dashboard.ver',
                'descripcion' => 'Consultar dashboard.',
                'activo' => 1,
                'es_sistema' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'clave' => 'catalogos.administrar',
                'descripcion' => 'Administrar catálogos base.',
                'activo' => 1,
                'es_sistema' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'clave' => 'documentos.eliminar',
                'descripcion' => 'Dar de baja documentos.',
                'activo' => 1,
                'es_sistema' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('permission_role')->insert([
            ['role_id' => 2, 'permission_id' => 1],
            ['role_id' => 3, 'permission_id' => 1],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function permissionKeysForRole(int $roleId): array
    {
        return DB::table('permissions as p')
            ->join('permission_role as pr', 'pr.permission_id', '=', 'p.id')
            ->where('pr.role_id', $roleId)
            ->orderBy('p.clave')
            ->pluck('p.clave')
            ->map(fn ($key): string => (string) $key)
            ->all();
    }
}
