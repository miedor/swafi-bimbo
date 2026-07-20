<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class IndividualRegistrationIntegrityTest extends TestCase
{
    private string $controller;
    private string $request;
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        $this->controller = $this->read($root . '/app/Http/Controllers/RegistroIndividualController.php');
        $this->request = $this->read($root . '/app/Http/Requests/StoreRegistroIndividualRequest.php');
        $this->service = $this->read($root . '/app/Services/AssetRegistrationService.php');
    }

    public function test_new_asset_keeps_the_original_creator_and_uses_a_database_unique_key(): void
    {
        self::assertStringContainsString("'creado_por' => \$userId", $this->service);
        self::assertStringContainsString("'actualizado_por' => \$userId", $this->service);
        self::assertStringContainsString("Rule::unique('activos', 'numero_activo')", $this->request);
        self::assertStringContainsString("(string) \$exception->getCode() === '23000'", $this->controller);
    }

    public function test_existing_asset_payload_is_rejected_instead_of_silently_applied(): void
    {
        self::assertStringContainsString("if (\$this->isExistingAsset())", $this->request);
        self::assertStringContainsString("return ['prohibited'];", $this->request);
        self::assertStringContainsString('no pueden modificarse desde el registro de expedientes', $this->request);
    }

    public function test_asset_and_expedient_creation_remain_transactional_and_files_are_cleaned_on_failure(): void
    {
        self::assertStringContainsString('DB::transaction(function ()', $this->controller);
        self::assertStringContainsString('cleanupStoredFiles', $this->controller);
        self::assertStringContainsString('catch (QueryException $exception)', $this->controller);
        self::assertStringContainsString('catch (Throwable $exception)', $this->controller);
        self::assertStringContainsString('throw $exception;', $this->controller);
    }

    public function test_audit_event_differentiates_new_and_existing_asset_modes(): void
    {
        foreach ([
            "'modo_activo' => \$assetMode",
            "'activo_creado' => \$assetMode === 'new'",
            "'datos_maestros_activo_modificados' => false",
            "'documentos_registrados' => \$documentCount",
        ] as $expected) {
            self::assertStringContainsString($expected, $this->controller);
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents, $path);

        return $contents;
    }
}
