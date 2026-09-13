<?php

namespace Tests\Unit;

use App\Services\AssetRegistrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Pcb009WhiteBoxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestSchema();
        $this->createTestSchema();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->dropTestSchema();

        parent::tearDown();
    }

    /**
     * Camino 1: I -> 1 -> 2 -> 3 -> F.
     * IN: numeroActivo=" pcb-0001 ".
     * OUT: arreglo del activo vigente normalizado.
     */
    public function test_camino_1_retorna_el_activo_vigente_con_numero_normalizado(): void
    {
        $result = app(AssetRegistrationService::class)
            ->lookupActive(' pcb-0001 ');

        self::assertIsArray($result);
        self::assertSame('PCB-0001', $result['numero_activo']);
        self::assertSame('Motor de prueba SWAFI', $result['descripcion']);
        self::assertSame(0, $result['expedientes_vigentes']);
    }

    /**
     * Camino 2: I -> 1 -> 2 -> 4 -> F.
     * IN: numeroActivo="PCB-9999".
     * OUT: null porque el activo no existe.
     */
    public function test_camino_2_retorna_null_cuando_el_activo_no_existe(): void
    {
        $result = app(AssetRegistrationService::class)
            ->lookupActive('PCB-9999');

        self::assertNull($result);
    }

    /**
     * Camino 2 con un dato adicional del mismo camino.
     * IN: numeroActivo="PCB-0009".
     * OUT: null porque el activo existe, pero está inactivo.
     */
    public function test_camino_2_retorna_null_cuando_el_activo_esta_inactivo(): void
    {
        $result = app(AssetRegistrationService::class)
            ->lookupActive('PCB-0009');

        self::assertNull($result);
    }

    private function createTestSchema(): void
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

    private function seedTestData(): void
    {
        DB::table('tipos_activo')->insert([
            'id' => 1,
            'clave' => 'ME',
            'descripcion' => 'Maquinaria y equipo',
        ]);

        DB::table('proveedores')->insert([
            'id' => 1,
            'rfc' => 'P010101010',
            'nombre' => 'Proveedor de prueba',
        ]);

        DB::table('centros_costo')->insert([
            'id' => 1,
            'clave' => 'CC-01',
            'descripcion' => 'Centro de prueba',
        ]);

        DB::table('plantas')->insert([
            'id' => 1,
            'clave' => 'PL-01',
            'nombre' => 'Planta de prueba',
        ]);

        DB::table('ubicaciones')->insert([
            'id' => 1,
            'codigo_interno' => 'U-01',
            'descripcion' => 'Ubicación de prueba',
            'edificio' => null,
            'piso' => null,
            'pasillo' => null,
        ]);

        DB::table('responsables')->insert([
            'id' => 1,
            'nombre' => 'Responsable de prueba',
            'correo' => 'responsable@example.test',
        ]);

        DB::table('estatus_operativos')->insert([
            'clave' => 'en_operacion',
            'nombre' => 'En operación',
        ]);

        $timestamp = '2026-09-10 12:00:00';

        DB::table('activos')->insert([
            $this->assetRow(
                number: 'PCB-0001',
                description: 'Motor de prueba SWAFI',
                active: true,
                timestamp: $timestamp
            ),
            $this->assetRow(
                number: 'PCB-0009',
                description: 'Activo inactivo de prueba',
                active: false,
                timestamp: $timestamp
            ),
        ]);
    }

    private function assetRow(
        string $number,
        string $description,
        bool $active,
        string $timestamp
    ): array {
        return [
            'numero_activo' => $number,
            'tipo_activo_id' => 1,
            'proveedor_id' => 1,
            'centro_costo_id' => 1,
            'planta_id' => 1,
            'ubicacion_id' => 1,
            'responsable_id' => 1,
            'descripcion' => $description,
            'serie' => 'SER-' . $number,
            'marca' => 'Marca de prueba',
            'modelo' => 'Modelo de prueba',
            'fecha_adquisicion' => '2026-01-15',
            'estatus_operativo' => 'en_operacion',
            'estatus_documental' => 'completo',
            'activo' => $active,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function dropTestSchema(): void
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
    }
}
