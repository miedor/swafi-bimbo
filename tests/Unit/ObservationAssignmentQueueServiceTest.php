<?php

namespace Tests\Unit;

use App\Services\ObservationAssignmentQueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ObservationAssignmentQueueServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropSchema();
        $this->createSchema();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();

        parent::tearDown();
    }

    public function test_plant_user_sees_only_its_pending_assigned_observations(): void
    {
        $result = app(ObservationAssignmentQueueService::class)
            ->pendingForUser(30);

        self::assertSame(2, $result['total']);
        self::assertCount(2, $result['items']);
        self::assertSame([2, 3], $result['items']->pluck('observacion_id')->all());
        self::assertSame(
            ['Usuario Planta / Inventarios'],
            $result['items']->pluck('rol_destino')->unique()->values()->all()
        );
        self::assertNotContains(1, $result['items']->pluck('observacion_id')->all());
        self::assertNotContains(4, $result['items']->pluck('observacion_id')->all());
    }

    public function test_capture_user_keeps_its_own_assignment_queue_independent_from_plant(): void
    {
        $result = app(ObservationAssignmentQueueService::class)
            ->pendingForUser(20);

        self::assertSame(1, $result['total']);
        self::assertSame([1], $result['items']->pluck('observacion_id')->all());
        self::assertSame('Usuario Captura', $result['items']->first()->rol_destino);
    }

    private function seedData(): void
    {
        DB::table('users')->insert([
            [
                'id' => 10,
                'name' => 'Usuario Auditoría',
                'email' => 'auditoria@bimbo.test',
            ],
            [
                'id' => 20,
                'name' => 'Usuario Captura',
                'email' => 'captura@bimbo.test',
            ],
            [
                'id' => 30,
                'name' => 'Usuario Planta',
                'email' => 'planta@bimbo.test',
            ],
        ]);

        DB::table('activos')->insert([
            'numero_activo' => 'BIM-000001',
            'descripcion' => 'Equipo de empaque',
        ]);

        DB::table('expedientes')->insert([
            'id' => 1,
            'numero_activo' => 'BIM-000001',
            'folio_factura' => 'FAC-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('expediente_observaciones')->insert([
            [
                'id' => 1,
                'expediente_id' => 1,
                'numero_activo' => 'BIM-000001',
                'tipo_observacion' => 'falta_pdf',
                'prioridad' => 'media',
                'rol_destino' => 'Usuario Captura',
                'asignado_a' => 20,
                'estatus' => 'abierta',
                'descripcion' => 'Falta incorporar el documento PDF.',
                'creado_por' => 10,
                'fecha_asignacion' => now(),
                'fecha_compromiso' => now()->addDays(4)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'expediente_id' => 1,
                'numero_activo' => 'BIM-000001',
                'tipo_observacion' => 'falta_ubicacion',
                'prioridad' => 'critica',
                'rol_destino' => 'Usuario Planta / Inventarios',
                'asignado_a' => 30,
                'estatus' => 'rechazada',
                'descripcion' => 'La ubicación indicada no cuenta con evidencia.',
                'creado_por' => 10,
                'fecha_asignacion' => now(),
                'fecha_compromiso' => now()->addDay()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'expediente_id' => 1,
                'numero_activo' => 'BIM-000001',
                'tipo_observacion' => 'ubicacion_incorrecta',
                'prioridad' => 'alta',
                'rol_destino' => 'Usuario Planta / Inventarios',
                'asignado_a' => 30,
                'estatus' => 'en_atencion',
                'descripcion' => 'La ubicación del activo debe ser verificada.',
                'creado_por' => 10,
                'fecha_asignacion' => now(),
                'fecha_compromiso' => now()->addDays(2)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'expediente_id' => 1,
                'numero_activo' => 'BIM-000001',
                'tipo_observacion' => 'falta_ubicacion',
                'prioridad' => 'alta',
                'rol_destino' => 'Usuario Planta / Inventarios',
                'asignado_a' => 30,
                'estatus' => 'atendida',
                'descripcion' => 'La ubicación ya fue corregida y espera validación.',
                'creado_por' => 10,
                'fecha_asignacion' => now(),
                'fecha_compromiso' => now()->addDays(2)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        Schema::create('activos', function (Blueprint $table): void {
            $table->string('numero_activo')->primary();
            $table->string('descripcion')->nullable();
        });

        Schema::create('expedientes', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_activo');
            $table->string('folio_factura')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('expediente_observaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('expediente_id');
            $table->string('numero_activo');
            $table->string('tipo_observacion');
            $table->string('prioridad');
            $table->string('rol_destino')->nullable();
            $table->unsignedBigInteger('asignado_a')->nullable();
            $table->string('estatus');
            $table->text('descripcion');
            $table->text('respuesta_atencion')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamp('fecha_asignacion')->nullable();
            $table->date('fecha_compromiso')->nullable();
            $table->timestamp('fecha_notificacion')->nullable();
            $table->text('notificacion_error')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        foreach ([
            'expediente_observaciones',
            'expedientes',
            'activos',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
