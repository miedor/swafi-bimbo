<?php

namespace Tests\Unit;

use App\Services\SafeExceptionReporter;
use App\Services\ValorActivoHistoryService;
use JsonException;
use Tests\TestCase;
use Throwable;

class Pcb013WhiteBoxTest extends TestCase
{
    private ValorActivoHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ValorActivoHistoryService();
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance(SafeExceptionReporter::class);

        parent::tearDown();
    }

    /**
     * Camino 1: I -> 1 -> 2 -> F.
     * IN: payload=["valor_fiscal"=>100].
     * OUT: ["valor_fiscal"=>100].
     */
    public function test_camino_1_retorna_sin_cambios_un_payload_que_ya_es_arreglo(): void
    {
        $payload = ['valor_fiscal' => 100];

        self::assertSame($payload, $this->service->decodePayload($payload));
    }

    /**
     * Camino 2: I -> 1 -> 3 -> 4 -> F.
     * IN: payload=(object)["valor_fiscal"=>100].
     * OUT: ["valor_fiscal"=>100].
     */
    public function test_camino_2_convierte_un_objeto_en_arreglo(): void
    {
        $payload = (object) ['valor_fiscal' => 100];

        self::assertSame(
            ['valor_fiscal' => 100],
            $this->service->decodePayload($payload)
        );
    }

    /**
     * Camino 3: I -> 1 -> 3 -> 5 -> 7 -> F.
     * IN: payload=null.
     * OUT: [].
     */
    public function test_camino_3_retorna_arreglo_vacio_para_null(): void
    {
        self::assertSame([], $this->service->decodePayload(null));
    }

    /**
     * Camino 4: I -> 1 -> 3 -> 5 -> 6 -> 7 -> F.
     * IN: payload="   ".
     * OUT: [].
     */
    public function test_camino_4_retorna_arreglo_vacio_para_una_cadena_en_blanco(): void
    {
        self::assertSame([], $this->service->decodePayload('   '));
    }

    /**
     * Camino 5: I -> 1 -> 3 -> 5 -> 6 -> 8 -> 9 -> 10 -> 11 -> F.
     * IN: payload='{"valor_fiscal":100}'.
     * OUT: ["valor_fiscal"=>100].
     */
    public function test_camino_5_decodifica_un_json_cuyo_resultado_es_arreglo(): void
    {
        self::assertSame(
            ['valor_fiscal' => 100],
            $this->service->decodePayload('{"valor_fiscal":100}')
        );
    }

    /**
     * Camino 6: I -> 1 -> 3 -> 5 -> 6 -> 8 -> 9 -> 10 -> 7 -> F.
     * IN: payload="100".
     * OUT: [].
     */
    public function test_camino_6_retorna_arreglo_vacio_si_el_json_es_escalar(): void
    {
        self::assertSame([], $this->service->decodePayload('100'));
    }

    /**
     * Camino 7: I -> 1 -> 3 -> 5 -> 6 -> 8 -> 9 -> 12 -> 7 -> F.
     * IN: payload="{json-invalido".
     * OUT: [] y una advertencia controlada en SafeExceptionReporter.
     */
    public function test_camino_7_controla_json_invalido_y_registra_la_advertencia(): void
    {
        $payload = '{json-invalido';

        $reporter = new class
        {
            /** @var array<int, array<string, mixed>> */
            public array $warnings = [];

            /**
             * @param array<string, mixed> $context
             */
            public function warning(
                Throwable $exception,
                string $operation,
                array $context = []
            ): string {
                $this->warnings[] = [
                    'exception_class' => $exception::class,
                    'operation' => $operation,
                    'context' => $context,
                ];

                return 'pcb013-test-warning';
            }
        };

        $this->app->instance(SafeExceptionReporter::class, $reporter);

        self::assertSame([], $this->service->decodePayload($payload));
        self::assertSame([
            [
                'exception_class' => JsonException::class,
                'operation' => 'asset_value_history_snapshot_decode',
                'context' => [
                    'payload_length' => strlen($payload),
                ],
            ],
        ], $reporter->warnings);
    }
}
