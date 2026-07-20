<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RepositoryRootHygieneTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function test_misplaced_duplicate_files_are_not_present_in_repository_root(): void
    {
        foreach ([
            'BulkRegistrationXlsxConfigurationTest.php',
            'ImportacionMasiva.php',
            'PtiiXssProtectionConfigurationTest.php',
            'SimpleXlsxReader.php',
            'SwafiAuth.php',
            'registro-masivo.blade.php',
            'web.php',
        ] as $file) {
            self::assertFileDoesNotExist(
                $this->projectRoot.'/'.$file,
                "El archivo huérfano {$file} no debe permanecer en la raíz del repositorio."
            );
        }
    }

    public function test_canonical_files_remain_available_in_their_laravel_paths(): void
    {
        foreach ([
            'app/Models/ImportacionMasiva.php',
            'routes/web.php',
            'app/Services/RegistroMasivoService.php',
            'app/Http/Middleware/SwafiAuth.php',
            'resources/views/swafi/registro-masivo.blade.php',
            'app/Http/Requests/ImportRegistroMasivoRequest.php',
            'database/migrations/2026_07_20_000580_add_xlsx_format_to_bulk_imports.php',
            'tests/Unit/BulkRegistrationXlsxConfigurationTest.php',
            'tests/Unit/PtiiXssProtectionConfigurationTest.php',
        ] as $relativePath) {
            self::assertFileExists(
                $this->projectRoot.'/'.$relativePath,
                "Falta el archivo canónico requerido: {$relativePath}."
            );
        }
    }
}
