<?php

namespace Tests\Unit;

use App\Services\SwafiStorageService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class Pcb010WhiteBoxTest extends TestCase
{
    private const DISK = 'local';

    private const PATH = 'expedientes/PCB-0001/factura.pdf';

    private const FILE_CONTENT = 'abc';

    private const FILE_SHA256 =
        'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISK);
        Storage::disk(self::DISK)->put(self::PATH, self::FILE_CONTENT);
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
     * Camino 1: I -> 1 -> 2 -> 4 -> F.
     * IN: expectedHash="   ".
     * OUT: false, sin invocar hash() ni leer el archivo.
     */
    public function test_camino_1_rechaza_un_hash_vacio_sin_leer_el_archivo(): void
    {
        $service = $this->serviceThatMustNotHash();

        self::assertFalse(
            $service->verifyHash(self::DISK, self::PATH, '   ')
        );
    }

    /**
     * Camino 2: I -> 1 -> 2 -> 3 -> 4 -> F.
     * IN: expectedHash="abc123".
     * OUT: false, sin invocar hash() ni leer el archivo.
     */
    public function test_camino_2_rechaza_un_hash_invalido_sin_leer_el_archivo(): void
    {
        $service = $this->serviceThatMustNotHash();

        self::assertFalse(
            $service->verifyHash(self::DISK, self::PATH, 'abc123')
        );
    }

    /**
     * Camino 3: I -> 1 -> 2 -> 3 -> 5 -> 6 -> F.
     * IN: expectedHash=" " + strtoupper(H) + " ".
     * OUT: true; trim() y strtolower() normalizan el hash esperado.
     */
    public function test_camino_3_acepta_el_hash_correcto_normalizado(): void
    {
        $expectedHash = ' ' . strtoupper(self::FILE_SHA256) . ' ';

        self::assertTrue(
            app(SwafiStorageService::class)->verifyHash(
                self::DISK,
                self::PATH,
                $expectedHash
            )
        );
    }

    /**
     * Camino 3 con una segunda fila de datos.
     * IN: expectedHash con 64 ceros.
     * OUT: false porque el formato es válido, pero no coincide con H.
     */
    public function test_camino_3_rechaza_un_hash_valido_distinto(): void
    {
        $differentHash = str_repeat('0', 64);

        self::assertFalse(
            app(SwafiStorageService::class)->verifyHash(
                self::DISK,
                self::PATH,
                $differentHash
            )
        );
    }

    /**
     * Crea un doble parcial que hace fallar la prueba si verifyHash()
     * intenta calcular el hash en los caminos de rechazo temprano.
     */
    private function serviceThatMustNotHash(): SwafiStorageService
    {
        $service = Mockery::mock(SwafiStorageService::class)->makePartial();
        $service->shouldNotReceive('hash');

        return $service;
    }
}
