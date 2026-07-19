<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;

class PtiiExceptionHandlingCoverageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_application_does_not_use_the_generic_report_helper_for_caught_exceptions(): void
    {
        foreach ($this->phpFiles($this->root.'/app') as $path => $contents) {
            self::assertStringNotContainsString('report($exception)', $contents, $path);
        }
    }

    public function test_every_non_business_catch_is_reported_or_rethrown(): void
    {
        foreach ($this->phpFiles($this->root.'/app') as $path => $contents) {
            foreach ($this->catchBlocks($contents) as $catch) {
                if (str_contains($catch['signature'], 'DomainException')
                    || str_contains($catch['signature'], 'ValidationException')) {
                    continue;
                }

                $body = $catch['body'];
                $handled = str_contains($body, 'SafeExceptionReporter')
                    || str_contains($body, 'safeExceptions')
                    || preg_match('/\bthrow\b/', $body) === 1
                    || str_contains($body, 'error_log(')
                    || str_contains($body, '$this->error(')
                    || str_contains($body, '$this->warn(');

                self::assertTrue(
                    $handled,
                    "Excepción técnica sin tratamiento seguro en {$path}: {$catch['signature']}"
                );
            }
        }
    }

    public function test_user_facing_technical_failures_include_a_safe_support_reference(): void
    {
        $expectations = [
            'app/Http/Controllers/BusquedaController.php' => ['advanced_search_excel_export'],
            'app/Http/Controllers/CatalogosController.php' => [
                'catalog_import_preview',
                'catalog_import_apply',
                'catalog_import_cancel',
                'catalog_import_incidents_excel',
                'catalog_import_incidents_csv',
            ],
            'app/Http/Controllers/PasswordResetController.php' => ['password_reset_mail_send'],
            'app/Http/Controllers/RegistroMasivoController.php' => [
                'bulk_import_preview',
                'bulk_import_apply',
                'bulk_import_rollback',
                'bulk_import_incidents_excel',
            ],
            'app/Http/Controllers/ReportesController.php' => ['report_center_excel_export'],
            'app/Http/Controllers/SeguridadController.php' => ['audit_log_export'],
            'app/Http/Controllers/ValorActivoExportController.php' => ['asset_value_sheet_export'],
            'app/Http/Controllers/ValoresActivoController.php' => ['asset_values_bulk_import'],
            'app/Services/TransferNotificationService.php' => ['transfer_approver_notification_send'],
        ];

        foreach ($expectations as $path => $operations) {
            $contents = $this->read($path);
            self::assertStringContainsString('Referencia:', $contents, $path);

            foreach ($operations as $operation) {
                self::assertStringContainsString("'{$operation}'", $contents, "{$path}:{$operation}");
            }
        }
    }

    public function test_notification_failures_store_only_safe_support_references(): void
    {
        foreach ([
            'app/Http/Controllers/ExpedienteObservacionController.php',
            'app/Http/Controllers/UbicacionInventarioController.php',
            'app/Services/TransferNotificationService.php',
            'app/Services/SwafiStorageService.php',
        ] as $path) {
            $contents = $this->read($path);
            self::assertStringContainsString('Referencia:', $contents, $path);
            self::assertStringNotContainsString("'error' => \$exception->getMessage()", $contents, $path);
            self::assertStringNotContainsString("'notificacion_error' => \$exception->getMessage()", $contents, $path);
        }
    }

    /** @return array<string,string> */
    private function phpFiles(string $base): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $files[str_replace($this->root.'/', '', $file->getPathname())] = $contents;
        }

        return $files;
    }

    /** @return array<int,array{signature:string,body:string}> */
    private function catchBlocks(string $contents): array
    {
        $blocks = [];
        $offset = 0;

        while (preg_match('/catch\s*\(([^)]*)\)\s*\{/', $contents, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $signature = $match[1][0];
            $openingBrace = $match[0][1] + strlen($match[0][0]) - 1;
            $depth = 1;
            $cursor = $openingBrace + 1;
            $length = strlen($contents);

            while ($cursor < $length && $depth > 0) {
                if ($contents[$cursor] === '{') {
                    $depth++;
                } elseif ($contents[$cursor] === '}') {
                    $depth--;
                }
                $cursor++;
            }

            $blocks[] = [
                'signature' => $signature,
                'body' => substr($contents, $openingBrace + 1, max(0, $cursor - $openingBrace - 2)),
            ];
            $offset = $cursor;
        }

        return $blocks;
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root.'/'.$relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}
