<?php

namespace Tests\Unit;

use App\Models\Activo;
use App\Services\InitialAssetLocationService;
use App\Services\InventoryPeriodService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class Pcb012WhiteBoxTest extends TestCase
{
    private Activo $asset;

    private Carbon $assignmentDate;

    private ReflectionMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asset = new Activo();
        $this->asset->forceFill([
            'numero_activo' => 'PCB-0012',
            'planta_id' => 1,
            'ubicacion_id' => null,
            'activo' => true,
        ]);

        $this->assignmentDate = Carbon::parse('2026-09-10')->startOfDay();

        $this->method = new ReflectionMethod(
            InitialAssetLocationService::class,
            'assertInitialMovementAllowed'
        );
        $this->method->setAccessible(true);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Camino 1: I -> 1 -> 2 -> 3 -> F.
     * IN: el doble retorna {id:12, planta_id:1}.
     * OUT: el mismo objeto retornado por el doble.
     */
    public function test_camino_1_retorna_el_mismo_objeto_ubicacion_del_doble(): void
    {
        $destination = (object) [
            'id' => 12,
            'planta_id' => 1,
        ];

        $service = $this->serviceReturning($destination);

        $result = $this->invokePrivateMethod($service);

        self::assertSame($destination, $result);
    }

    /**
     * Camino 2: I -> 1 -> 2 -> 4 -> 5 -> 12 -> F.
     * IN: el doble lanza ValidationException con errors()=[].
     * OUT: ValidationException con errors()=[].
     */
    public function test_camino_2_conserva_una_lista_de_errores_vacia(): void
    {
        $service = $this->serviceThrowing([]);

        $exception = $this->captureValidationException($service);

        self::assertSame([], $exception->errors());
    }

    /**
     * Camino 3: mapeo de ubicacion_destino_id a ubicacion_id.
     * IN: {ubicacion_destino_id:["Destino inválido"]}.
     * OUT: {ubicacion_id:["Destino inválido"]}.
     */
    public function test_camino_3_mapea_ubicacion_destino_a_ubicacion_del_formulario(): void
    {
        $service = $this->serviceThrowing([
            'ubicacion_destino_id' => ['Destino inválido'],
        ]);

        $exception = $this->captureValidationException($service);

        self::assertSame([
            'ubicacion_id' => ['Destino inválido'],
        ], $exception->errors());
    }

    /**
     * Camino 4: mapeo de fecha_movimiento a fecha_asignacion.
     * IN: {fecha_movimiento:["Periodo bloqueado"]}.
     * OUT: {fecha_asignacion:["Periodo bloqueado"]}.
     */
    public function test_camino_4_mapea_fecha_movimiento_a_fecha_asignacion(): void
    {
        $service = $this->serviceThrowing([
            'fecha_movimiento' => ['Periodo bloqueado'],
        ]);

        $exception = $this->captureValidationException($service);

        self::assertSame([
            'fecha_asignacion' => ['Periodo bloqueado'],
        ], $exception->errors());
    }

    /**
     * Camino 5: conservar un campo que no requiere mapeo.
     * IN: {numero_activo:["Activo inactivo"]}.
     * OUT: {numero_activo:["Activo inactivo"]}.
     */
    public function test_camino_5_conserva_el_nombre_del_campo_numero_activo(): void
    {
        $service = $this->serviceThrowing([
            'numero_activo' => ['Activo inactivo'],
        ]);

        $exception = $this->captureValidationException($service);

        self::assertSame([
            'numero_activo' => ['Activo inactivo'],
        ], $exception->errors());
    }

    private function serviceReturning(object $destination): InitialAssetLocationService
    {
        $inventoryPeriods = Mockery::mock(InventoryPeriodService::class);
        $inventoryPeriods
            ->shouldReceive('assertMovementAllowed')
            ->once()
            ->with($this->asset, 12, $this->assignmentDate)
            ->andReturn($destination);

        return new InitialAssetLocationService($inventoryPeriods);
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private function serviceThrowing(array $errors): InitialAssetLocationService
    {
        $inventoryPeriods = Mockery::mock(InventoryPeriodService::class);
        $inventoryPeriods
            ->shouldReceive('assertMovementAllowed')
            ->once()
            ->with($this->asset, 12, $this->assignmentDate)
            ->andThrow(ValidationException::withMessages($errors));

        return new InitialAssetLocationService($inventoryPeriods);
    }

    private function invokePrivateMethod(
        InitialAssetLocationService $service
    ): object {
        return $this->method->invoke(
            $service,
            $this->asset,
            12,
            $this->assignmentDate
        );
    }

    private function captureValidationException(
        InitialAssetLocationService $service
    ): ValidationException {
        try {
            $this->invokePrivateMethod($service);
        } catch (ValidationException $exception) {
            return $exception;
        }

        self::fail(
            'Se esperaba una ValidationException y el método terminó sin excepción.'
        );
    }
}
