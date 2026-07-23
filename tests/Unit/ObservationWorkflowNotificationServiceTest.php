<?php

namespace Tests\Unit;

use App\Mail\SwafiObservacionAtendidaMail;
use App\Mail\SwafiObservacionResolucionMail;
use App\Models\ExpedienteObservacion;
use App\Services\ObservationWorkflowNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ObservationWorkflowNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mail.default', 'smtp');
        Mail::fake();

        $this->dropSchema();
        $this->createSchema();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();

        parent::tearDown();
    }

    public function test_attended_observation_notifies_the_creator_and_records_traceability(): void
    {
        $observation = ExpedienteObservacion::query()->findOrFail(1);

        $result = app(ObservationWorkflowNotificationService::class)
            ->notifyCreatorForValidation($observation, 20);

        self::assertTrue($result['sent']);
        self::assertSame('auditoria@bimbo.test', $result['recipient_email']);

        $fresh = $observation->fresh();
        self::assertNotNull($fresh->fecha_notificacion_revision);
        self::assertSame(1, $fresh->notificacion_revision_intentos);
        self::assertNull($fresh->notificacion_revision_error_referencia);

        Mail::assertSent(
            SwafiObservacionAtendidaMail::class,
            static fn (SwafiObservacionAtendidaMail $mail): bool =>
                $mail->numeroActivo === 'BIM-000001'
                && $mail->respuestaAtencion === 'Se incorporó el documento faltante.'
                && str_contains($mail->urlExpediente, '#observacion-1')
        );

        $audit = DB::table('bitacora_auditoria')
            ->where('accion', 'NOTIF_OBS_REVISION_ENVIADA')
            ->first();

        self::assertNotNull($audit);
        self::assertStringContainsString('auditoria@bimbo.test', (string) $audit->despues);
    }

    public function test_closed_observation_notifies_the_assigned_user_of_the_resolution(): void
    {
        $observation = ExpedienteObservacion::query()->findOrFail(1);
        $observation->update([
            'estatus' => 'cerrada',
            'comentario_validacion' => 'La corrección fue revisada y aceptada.',
            'validado_por' => 10,
            'fecha_validacion' => now(),
        ]);

        $result = app(ObservationWorkflowNotificationService::class)
            ->notifyAssigneeOfResolution($observation, 10);

        self::assertTrue($result['sent']);
        self::assertSame('captura@bimbo.test', $result['recipient_email']);

        $fresh = $observation->fresh();
        self::assertNotNull($fresh->fecha_notificacion_resolucion);
        self::assertSame(1, $fresh->notificacion_resolucion_intentos);
        self::assertNull($fresh->notificacion_resolucion_error_referencia);

        Mail::assertSent(
            SwafiObservacionResolucionMail::class,
            static fn (SwafiObservacionResolucionMail $mail): bool =>
                $mail->decision === 'Cerrada'
                && $mail->comentarioValidacion === 'La corrección fue revisada y aceptada.'
                && str_contains($mail->urlExpediente, '#observacion-1')
        );
    }

    public function test_rejected_observation_notifies_the_assigned_user_to_resume_attention(): void
    {
        $observation = ExpedienteObservacion::query()->findOrFail(1);
        $observation->update([
            'estatus' => 'rechazada',
            'comentario_validacion' => 'La evidencia no permite comprobar la corrección.',
            'validado_por' => 10,
            'fecha_validacion' => now(),
        ]);

        $result = app(ObservationWorkflowNotificationService::class)
            ->notifyAssigneeOfResolution($observation, 10);

        self::assertTrue($result['sent']);

        Mail::assertSent(
            SwafiObservacionResolucionMail::class,
            static fn (SwafiObservacionResolucionMail $mail): bool =>
                $mail->decision === 'Rechazada'
                && str_contains($mail->comentarioValidacion, 'evidencia')
        );
    }

    public function test_plant_assignee_returns_the_attended_observation_to_audit_and_receives_the_resolution(): void
    {
        $observation = ExpedienteObservacion::query()->findOrFail(2);

        $reviewResult = app(ObservationWorkflowNotificationService::class)
            ->notifyCreatorForValidation($observation, 30);

        self::assertTrue($reviewResult['sent']);
        self::assertSame('auditoria@bimbo.test', $reviewResult['recipient_email']);

        Mail::assertSent(
            SwafiObservacionAtendidaMail::class,
            static fn (SwafiObservacionAtendidaMail $mail): bool =>
                $mail->hasTo('auditoria@bimbo.test')
                && $mail->numeroActivo === 'BIM-000001'
                && str_contains($mail->tipoObservacion, 'Ubicación')
        );

        $observation->update([
            'estatus' => 'cerrada',
            'comentario_validacion' => 'La ubicación fue verificada y se acepta la corrección.',
            'validado_por' => 10,
            'fecha_validacion' => now(),
        ]);

        $resolutionResult = app(ObservationWorkflowNotificationService::class)
            ->notifyAssigneeOfResolution($observation, 10);

        self::assertTrue($resolutionResult['sent']);
        self::assertSame('planta@bimbo.test', $resolutionResult['recipient_email']);

        Mail::assertSent(
            SwafiObservacionResolucionMail::class,
            static fn (SwafiObservacionResolucionMail $mail): bool =>
                $mail->hasTo('planta@bimbo.test')
                && $mail->decision === 'Cerrada'
        );
    }

    private function seedData(): void
    {
        DB::table('users')->insert([
            [
                'id' => 10,
                'usuario' => 'auditor',
                'name' => 'Usuario Auditoría',
                'email' => 'auditoria@bimbo.test',
                'password' => 'hash',
                'estatus' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 20,
                'usuario' => 'captura',
                'name' => 'Usuario Captura',
                'email' => 'captura@bimbo.test',
                'password' => 'hash',
                'estatus' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 30,
                'usuario' => 'planta',
                'name' => 'Usuario Planta',
                'email' => 'planta@bimbo.test',
                'password' => 'hash',
                'estatus' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'Usuario Consulta / Auditoría', 'activo' => 1],
            ['id' => 2, 'nombre' => 'Usuario Captura', 'activo' => 1],
            ['id' => 3, 'nombre' => 'Usuario Planta / Inventarios', 'activo' => 1],
        ]);

        DB::table('permissions')->insert([
            ['id' => 1, 'clave' => 'observaciones.validar', 'activo' => 1],
            ['id' => 2, 'clave' => 'observaciones.atender', 'activo' => 1],
        ]);

        DB::table('role_user')->insert([
            ['role_id' => 1, 'user_id' => 10],
            ['role_id' => 2, 'user_id' => 20],
            ['role_id' => 3, 'user_id' => 30],
        ]);

        DB::table('permission_role')->insert([
            ['role_id' => 1, 'permission_id' => 1],
            ['role_id' => 2, 'permission_id' => 2],
            ['role_id' => 3, 'permission_id' => 2],
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
                'prioridad' => 'alta',
                'rol_destino' => 'Usuario Captura',
                'asignado_a' => 20,
                'estatus' => 'atendida',
                'descripcion' => 'Falta incorporar la evidencia documental de la factura.',
                'respuesta_atencion' => 'Se incorporó el documento faltante.',
                'creado_por' => 10,
                'atendido_por' => 20,
                'fecha_atencion' => now(),
                'notificacion_revision_intentos' => 0,
                'notificacion_resolucion_intentos' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'expediente_id' => 1,
                'numero_activo' => 'BIM-000001',
                'tipo_observacion' => 'falta_ubicacion',
                'prioridad' => 'alta',
                'rol_destino' => 'Usuario Planta / Inventarios',
                'asignado_a' => 30,
                'estatus' => 'atendida',
                'descripcion' => 'Falta confirmar la ubicación física del activo.',
                'respuesta_atencion' => 'Se verificó el activo y se actualizó su ubicación.',
                'creado_por' => 10,
                'atendido_por' => 30,
                'fecha_atencion' => now(),
                'notificacion_revision_intentos' => 0,
                'notificacion_resolucion_intentos' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('usuario')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('estatus')->default('activo');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('clave');
            $table->boolean('activo')->default(true);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
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
            $table->text('comentario_validacion')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('atendido_por')->nullable();
            $table->unsignedBigInteger('validado_por')->nullable();
            $table->unsignedBigInteger('cancelado_por')->nullable();
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamp('fecha_atencion')->nullable();
            $table->timestamp('fecha_asignacion')->nullable();
            $table->date('fecha_compromiso')->nullable();
            $table->timestamp('fecha_validacion')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamp('fecha_notificacion')->nullable();
            $table->timestamp('fecha_notificacion_revision')->nullable();
            $table->timestamp('ultimo_intento_notificacion_revision_at')->nullable();
            $table->unsignedInteger('notificacion_revision_intentos')->default(0);
            $table->string('notificacion_revision_error_referencia', 80)->nullable();
            $table->timestamp('fecha_notificacion_resolucion')->nullable();
            $table->timestamp('ultimo_intento_notificacion_resolucion_at')->nullable();
            $table->unsignedInteger('notificacion_resolucion_intentos')->default(0);
            $table->string('notificacion_resolucion_error_referencia', 80)->nullable();
            $table->timestamp('ultimo_intento_recordatorio_at')->nullable();
            $table->timestamp('fecha_ultimo_recordatorio')->nullable();
            $table->unsignedInteger('recordatorios_enviados')->default(0);
            $table->string('recordatorio_error_referencia')->nullable();
            $table->text('notificacion_error')->nullable();
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

    private function dropSchema(): void
    {
        foreach ([
            'bitacora_auditoria',
            'expediente_observaciones',
            'expedientes',
            'activos',
            'permission_role',
            'role_user',
            'permissions',
            'roles',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
