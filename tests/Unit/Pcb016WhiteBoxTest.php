<?php

namespace Tests\Unit;

use App\Services\ObservationDeadlineService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Pcb016WhiteBoxTest extends TestCase
{
    private ObservationDeadlineService $service;

    private CarbonImmutable $now;

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Mexico_City');

        $this->service = new ObservationDeadlineService();
        $this->now = CarbonImmutable::parse(
            '2026-09-10 12:00:00',
            'America/Mexico_City'
        );
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    /**
     * Ejecuta las ocho filas del Paso 4 con reloj y zona horaria fijos.
     */
    #[DataProvider('deadlineCases')]
    public function test_state_clasifica_el_seguimiento_esperado(
        ?string $deadline,
        string $status,
        string $expected
    ): void {
        $result = $this->service->state(
            $deadline,
            $status,
            $this->now,
            2
        );

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string|null, 1: string, 2: string}>
     */
    public static function deadlineCases(): array
    {
        return [
            'camino 1 - atendida pendiente de validación' => [
                '2026-09-09',
                'atendida',
                'pendiente_validacion',
            ],
            'camino 2 - cerrada finalizada' => [
                '2026-09-09',
                'cerrada',
                'finalizada',
            ],
            'camino 3 - fecha nula' => [
                null,
                'abierta',
                'sin_fecha',
            ],
            'camino 4 - fecha vacía' => [
                '   ',
                'abierta',
                'sin_fecha',
            ],
            'camino 5 - fecha vencida' => [
                '2026-09-09',
                'abierta',
                'vencida',
            ],
            'camino 6 - vence hoy' => [
                '2026-09-10',
                'en_atencion',
                'vence_hoy',
            ],
            'camino 7 - dentro de la ventana de aviso' => [
                '2026-09-12',
                'rechazada',
                'por_vencer',
            ],
            'camino 8 - fuera de la ventana de aviso' => [
                '2026-09-13',
                'abierta',
                'en_plazo',
            ],
        ];
    }
}
