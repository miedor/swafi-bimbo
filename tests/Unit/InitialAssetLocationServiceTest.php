<?php

namespace Tests\Unit;

use App\Services\InitialAssetLocationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InitialAssetLocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-20 12:00:00');

        $this->createSchema();
        $this->seedBaseData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ([
            'bitacora_auditoria',
            'periodos_inventario',
            'movimientos_ubicacion',
            'expedientes',
            'activos',
            'responsables',
            'ubicaciones',
            'areas',
            'plantas',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_confirm_assigns_the_location_once_and_registers_movement_and_audit(): void
    {
        $movement = app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: [
                'ubicacion_id' => 1,
                'responsable_id' => 1,
                'fecha_asignacion' => '2026-07-20',
                'motivo' => 'Ubicación inicial confirmada en la recepción del activo.',
                'evidencia' => 'Acta de recepción AR-2026-001.',
            ],
            userId: 7,
            ipAddress: '127.0.0.1'
        );

        self::assertSame(1, (int) $movement->ubicacion_destino_id);
        self::assertNull($movement->ubicacion_origen_id);
        self::assertSame(1, (int) $movement->responsable_id);

        $asset = DB::table('activos')->where('numero_activo', 'BIM-000001')->first();
        self::assertSame(1, (int) $asset->ubicacion_id);
        self::assertSame(1, (int) $asset->responsable_id);
        self::assertSame(7, (int) $asset->actualizado_por);

        $audit = DB::table('bitacora_auditoria')->first();
        self::assertSame('UBICACION_INICIAL_CONFIRMADA', $audit->accion);
        self::assertSame('movimientos_ubicacion', $audit->tabla_afectada);
        self::assertStringContainsString('"tipo_asignacion":"inicial"', (string) $audit->despues);
    }

    public function test_confirm_rejects_an_asset_that_already_has_a_current_location(): void
    {
        DB::table('activos')->where('numero_activo', 'BIM-000001')->update([
            'ubicacion_id' => 1,
        ]);

        $this->expectException(ValidationException::class);

        app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: $this->validData(),
            userId: 7
        );
    }

    public function test_confirm_rejects_a_cross_plant_initial_location(): void
    {
        $this->expectException(ValidationException::class);

        app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: array_merge($this->validData(), ['ubicacion_id' => 2]),
            userId: 7
        );
    }

    public function test_confirm_rejects_an_assignment_before_the_asset_acquisition_date(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La fecha de asignación inicial no puede ser anterior a la fecha de adquisición del activo.');

        app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: array_merge($this->validData(), ['fecha_asignacion' => '2026-01-14']),
            userId: 7
        );
    }

    public function test_confirm_rejects_an_inactive_responsible(): void
    {
        DB::table('responsables')->where('id', 1)->update(['estatus' => 'inactivo']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El responsable seleccionado no existe o se encuentra inactivo.');

        app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: $this->validData(),
            userId: 7
        );
    }

    public function test_confirm_rejects_a_locked_inventory_period_and_maps_the_error_to_the_form_field(): void
    {
        DB::table('periodos_inventario')->insert([
            'uuid' => 'periodo-bloqueado-1',
            'planta_id' => 1,
            'nombre' => 'Cierre julio 2026',
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'estatus' => 'bloqueado',
            'observaciones' => null,
            'motivo_bloqueo' => 'Cierre de inventario mensual.',
            'creado_por' => 7,
            'bloqueado_por' => 7,
            'bloqueado_at' => now(),
            'desbloqueado_por' => null,
            'desbloqueado_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(InitialAssetLocationService::class)->confirm(
                expedienteId: 1,
                data: $this->validData(),
                userId: 7
            );

            self::fail('La asignación debió ser rechazada por el periodo de inventario bloqueado.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('fecha_asignacion', $exception->errors());
            self::assertArrayNotHasKey('fecha_movimiento', $exception->errors());
            self::assertStringContainsString('Cierre julio 2026', $exception->errors()['fecha_asignacion'][0]);
        }
    }

    public function test_confirm_rejects_initial_assignment_when_movement_history_already_exists(): void
    {
        DB::table('movimientos_ubicacion')->insert([
            'numero_activo' => 'BIM-000001',
            'ubicacion_origen_id' => 1,
            'ubicacion_destino_id' => null,
            'motivo' => 'Movimiento histórico previo.',
            'evidencia' => null,
            'fecha_movimiento' => '2026-06-01 00:00:00',
            'responsable_id' => 1,
            'registrado_por' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(InitialAssetLocationService::class)->confirm(
            expedienteId: 1,
            data: $this->validData(),
            userId: 7
        );
    }

    private function validData(): array
    {
        return [
            'ubicacion_id' => 1,
            'responsable_id' => 1,
            'fecha_asignacion' => '2026-07-20',
            'motivo' => 'Ubicación inicial confirmada en la recepción del activo.',
            'evidencia' => null,
        ];
    }

    private function createSchema(): void
    {
        Schema::create('plantas', function (Blueprint $table): void {
            $table->id();
            $table->string('clave');
            $table->string('nombre');
            $table->string('estatus')->default('activo');
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('planta_id');
            $table->string('nombre');
            $table->string('estatus')->default('activo');
            $table->timestamps();
        });

        Schema::create('ubicaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('planta_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('codigo_interno')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('edificio')->nullable();
            $table->string('piso')->nullable();
            $table->string('pasillo')->nullable();
            $table->string('estatus')->default('activo');
            $table->timestamps();
        });

        Schema::create('responsables', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('estatus')->default('activo');
            $table->timestamps();
        });

        Schema::create('activos', function (Blueprint $table): void {
            $table->string('numero_activo')->primary();
            $table->unsignedBigInteger('planta_id');
            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('expedientes', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('movimientos_ubicacion', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo');
            $table->unsignedBigInteger('ubicacion_origen_id')->nullable();
            $table->unsignedBigInteger('ubicacion_destino_id')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->text('evidencia')->nullable();
            $table->timestamp('fecha_movimiento');
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('periodos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('planta_id');
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estatus')->default('abierto');
            $table->text('observaciones')->nullable();
            $table->text('motivo_bloqueo')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('bloqueado_por')->nullable();
            $table->timestamp('bloqueado_at')->nullable();
            $table->unsignedBigInteger('desbloqueado_por')->nullable();
            $table->timestamp('desbloqueado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bitacora_auditoria', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('modulo');
            $table->string('accion', 40);
            $table->string('tabla_afectada')->nullable();
            $table->string('registro_clave')->nullable();
            $table->longText('antes')->nullable();
            $table->longText('despues')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('fecha_evento');
            $table->timestamps();
        });
    }

    private function seedBaseData(): void
    {
        $now = now();

        DB::table('plantas')->insert([
            ['id' => 1, 'clave' => 'PL-01', 'nombre' => 'Planta Centro', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'clave' => 'PL-02', 'nombre' => 'Planta Norte', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('areas')->insert([
            ['id' => 1, 'planta_id' => 1, 'nombre' => 'Producción', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'planta_id' => 2, 'nombre' => 'Almacén', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('ubicaciones')->insert([
            ['id' => 1, 'planta_id' => 1, 'area_id' => 1, 'codigo_interno' => 'U-01', 'descripcion' => 'Línea 1', 'edificio' => 'A', 'piso' => '1', 'pasillo' => 'P1', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'planta_id' => 2, 'area_id' => 2, 'codigo_interno' => 'U-02', 'descripcion' => 'Almacén norte', 'edificio' => 'B', 'piso' => '1', 'pasillo' => 'P2', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('responsables')->insert([
            ['id' => 1, 'nombre' => 'Responsable Uno', 'correo' => 'responsable@example.test', 'estatus' => 'activo', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('activos')->insert([
            'numero_activo' => 'BIM-000001',
            'planta_id' => 1,
            'ubicacion_id' => null,
            'responsable_id' => null,
            'fecha_adquisicion' => '2026-01-15',
            'activo' => true,
            'actualizado_por' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('expedientes')->insert([
            'id' => 1,
            'numero_activo' => 'BIM-000001',
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
