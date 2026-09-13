<?php

namespace Tests\Unit;

use App\Services\ValoresActivoImportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Pcb014WhiteBoxTest extends TestCase
{
    /**
     * Ejecuta las seis filas del Paso 4 como casos independientes.
     *
     * @param string|null $value Valor recibido desde la plantilla de importación.
     * @param int|null $expected Resultado esperado de toInteger().
     */
    #[DataProvider('integerCases')]
    public function test_to_integer_produce_el_resultado_esperado(
        ?string $value,
        ?int $expected
    ): void {
        $result = ValoresActivoImportService::toInteger($value);

        if ($expected === null) {
            self::assertNull($result);

            return;
        }

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string|null, 1: int|null}>
     */
    public static function integerCases(): array
    {
        return [
            'camino 1 - entero con espacios' => [' 36 ', 36],
            'camino 2 - número decimal' => ['36.5', null],
            'camino 2 - valor nulo' => [null, null],
            'camino 2 - cadena vacía' => ['', null],
            'camino 2 - texto no numérico' => ['abc', null],
            'camino 1 - entero cero' => ['0', 0],
        ];
    }
}
