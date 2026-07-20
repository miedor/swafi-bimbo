<?php

namespace Tests\Unit;

use App\Services\AssetRegistrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExistingAssetSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedCatalogsAndAssets();
    }

    protected function tearDown(): void
    {
        foreach ([
            'expedientes',
            'activos',
            'estatus_operativos',
            'responsables',
            'ubicaciones',
            'plantas',
            'centros_costo',
            'proveedores',
            'tipos_activo',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_search_filters_by_asset_prefix_and_returns_paginated_safe_summaries(): void
    {
        $result = app(AssetRegistrationService::class)->searchActive([
            'q' => 'bim-00',
            'page' => 1,
            'per_page' => 5,
        ]);

        self::assertSame(2, $result['meta']['total']);
        self::assertSame(1, $result['meta']['current_page']);
        self::assertSame(['BIM-000001', 'BIM-000002'], array_column($result['data'], 'numero_activo'));
        self::assertSame(2, $result['data'][0]['expedientes_vigentes']);
        self::assertArrayNotHasKey('monto_factura', $result['data'][0]);
        self::assertArrayNotHasKey('uuid_cfdi', $result['data'][0]);
    }

    public function test_search_combines_active_provider_and_plant_filters_and_excludes_inactive_assets(): void
    {
        $result = app(AssetRegistrationService::class)->searchActive([
            'proveedor_id' => 2,
            'planta_id' => 2,
            'page' => 1,
            'per_page' => 8,
        ]);

        self::assertSame(1, $result['meta']['total']);
        self::assertSame('EQP-100001', $result['data'][0]['numero_activo']);
        self::assertSame('Proveedor Dos (P020202020)', $result['data'][0]['labels']['proveedor']);
        self::assertSame('PL-02 · Planta Norte', $result['data'][0]['labels']['planta']);
    }

    public function test_invalid_service_page_size_falls_back_to_the_safe_default(): void
    {
        $result = app(AssetRegistrationService::class)->searchActive([
            'q' => 'BIM',
            'page' => 1,
            'per_page' => 5000,
        ]);

        self::assertSame(8, $result['meta']['per_page']);
    }

    private function createSchema(): void
    {
        Schema::create('tipos_activo', function (Blueprint $table): void {
            $table->id();
            $table->string('clave');
            $table->string('descripcion');
        });

        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('rfc');
            $table->string('nombre');
        });

        Schema::create('centros_costo', function (Blueprint $table): void {
            $table->id();
            $table->string('clave');
            $table->string('descripcion');
        });

        Schema::create('plantas', function (Blueprint $table): void {
            $table->id();
            $table->string('clave');
            $table->string('nombre');
        });

        Schema::create('ubicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_interno')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('edificio')->nullable();
            $table->string('piso')->nullable();
            $table->string('pasillo')->nullable();
        });

        Schema::create('responsables', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
        });

        Schema::create('estatus_operativos', function (Blueprint $table): void {
            $table->string('clave')->primary();
            $table->string('nombre');
        });

        Schema::create('activos', function (Blueprint $table): void {
            $table->string('numero_activo')->primary();
            $table->unsignedBigInteger('tipo_activo_id')->nullable();
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->unsignedBigInteger('planta_id')->nullable();
            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->string('descripcion');
            $table->string('serie')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->string('estatus_operativo')->nullable();
            $table->string('estatus_documental')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('expedientes', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo');
            $table->softDeletes();
        });
    }

    private function seedCatalogsAndAssets(): void
    {
        DB::table('tipos_activo')->insert([
            ['id' => 1, 'clave' => 'ME', 'descripcion' => 'Maquinaria y equipo'],
        ]);
        DB::table('proveedores')->insert([
            ['id' => 1, 'rfc' => 'P010101010', 'nombre' => 'Proveedor Uno'],
            ['id' => 2, 'rfc' => 'P020202020', 'nombre' => 'Proveedor Dos'],
        ]);
        DB::table('centros_costo')->insert([
            ['id' => 1, 'clave' => 'CC-01', 'descripcion' => 'Centro Uno'],
        ]);
        DB::table('plantas')->insert([
            ['id' => 1, 'clave' => 'PL-01', 'nombre' => 'Planta Centro'],
            ['id' => 2, 'clave' => 'PL-02', 'nombre' => 'Planta Norte'],
        ]);
        DB::table('ubicaciones')->insert([
            ['id' => 1, 'codigo_interno' => 'U-01', 'descripcion' => 'Almacén', 'edificio' => null, 'piso' => null, 'pasillo' => null],
        ]);
        DB::table('responsables')->insert([
            ['id' => 1, 'nombre' => 'Responsable Uno', 'correo' => 'responsable@example.test'],
        ]);
        DB::table('estatus_operativos')->insert([
            ['clave' => 'en_operacion', 'nombre' => 'En operación'],
        ]);

        $now = now();
        DB::table('activos')->insert([
            $this->asset('BIM-000001', 1, 1, true, $now),
            $this->asset('BIM-000002', 1, 1, true, $now),
            $this->asset('EQP-100001', 2, 2, true, $now),
            $this->asset('BIM-000099', 1, 1, false, $now),
        ]);

        DB::table('expedientes')->insert([
            ['numero_activo' => 'BIM-000001', 'deleted_at' => null],
            ['numero_activo' => 'BIM-000001', 'deleted_at' => null],
            ['numero_activo' => 'BIM-000001', 'deleted_at' => $now],
        ]);
    }

    private function asset(
        string $number,
        int $providerId,
        int $plantId,
        bool $active,
        mixed $timestamp
    ): array {
        return [
            'numero_activo' => $number,
            'tipo_activo_id' => 1,
            'proveedor_id' => $providerId,
            'centro_costo_id' => 1,
            'planta_id' => $plantId,
            'ubicacion_id' => 1,
            'responsable_id' => 1,
            'descripcion' => 'Activo ' . $number,
            'serie' => 'SER-' . $number,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'fecha_adquisicion' => '2026-01-15',
            'estatus_operativo' => 'en_operacion',
            'estatus_documental' => 'completo',
            'activo' => $active,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
