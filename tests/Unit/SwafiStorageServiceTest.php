<?php

namespace Tests\Unit;

use App\Services\SwafiStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SwafiStorageServiceTest extends TestCase
{
    public function test_store_uploaded_file_calculates_and_validates_sha256(): void
    {
        config()->set('filesystems.disks.swafi_test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/swafi_test'),
            'throw' => true,
        ]);
        config()->set('filesystems.swafi_disk', 'swafi_test');
        Storage::fake('swafi_test');

        $service = app(SwafiStorageService::class);
        $file = UploadedFile::fake()->createWithContent('factura.xml', '<cfdi>Total</cfdi>');

        $stored = $service->storeUploadedFile(
            file: $file,
            directory: 'swafi/pruebas',
            storedName: 'factura.xml'
        );

        $this->assertSame('swafi_test', $stored['disk']);
        $this->assertSame('swafi/pruebas/factura.xml', $stored['path']);
        $this->assertTrue(Storage::disk('swafi_test')->exists($stored['path']));

        $validation = $service->validate(
            $stored['disk'],
            $stored['path'],
            $stored['hash_sha256']
        );

        $this->assertTrue($validation['ok']);
    }

    public function test_validate_detects_hash_mismatch(): void
    {
        config()->set('filesystems.disks.swafi_test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/swafi_test'),
            'throw' => true,
        ]);
        Storage::fake('swafi_test');

        Storage::disk('swafi_test')->put('archivo.txt', 'contenido real');

        $service = app(SwafiStorageService::class);
        $validation = $service->validate(
            'swafi_test',
            'archivo.txt',
            hash('sha256', 'contenido diferente')
        );

        $this->assertFalse($validation['ok']);
        $this->assertStringContainsString('SHA-256', (string) $validation['message']);
    }

    public function test_copy_between_disks_preserves_integrity(): void
    {
        config()->set('filesystems.disks.source_test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/source_test'),
            'throw' => true,
        ]);
        config()->set('filesystems.disks.target_test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/target_test'),
            'throw' => true,
        ]);

        Storage::fake('source_test');
        Storage::fake('target_test');

        Storage::disk('source_test')->put('swafi/documento.pdf', 'PDF de prueba');
        $expectedHash = hash('sha256', 'PDF de prueba');

        $service = app(SwafiStorageService::class);
        $result = $service->copyBetweenDisks(
            sourceDisk: 'source_test',
            sourcePath: 'swafi/documento.pdf',
            targetDisk: 'target_test',
            expectedHash: $expectedHash
        );

        $this->assertSame($expectedHash, $result['hash_sha256']);
        $this->assertTrue(Storage::disk('target_test')->exists('swafi/documento.pdf'));
    }
}
