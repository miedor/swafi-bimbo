<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValoresActivoImportPreviewConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_preview_tables_preserve_batch_and_row_results(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_26_000680_create_value_import_preview_tables.php'
        );

        foreach ([
            "Schema::create('importaciones_valores'",
            "Schema::create('importacion_valores_filas'",
            "\$table->uuid('uuid')->unique()",
            "\$table->unsignedInteger('filas_correctas')",
            "\$table->unsignedInteger('filas_incorrectas')",
            "\$table->json('datos')",
            "\$table->json('errores')->nullable()",
            "\$table->boolean('aplicada')->default(false)",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_preview_and_application_are_separate_atomic_steps(): void
    {
        $service = $this->read('app/Services/ValoresActivoImportService.php');
        $preview = $this->section($service, 'public function previsualizar(', 'public function aplicar(');
        $apply = $this->section($service, 'public function aplicar(', 'public function cancelar(');

        foreach ([
            "'estado' => 'previsualizada'",
            "'estatus' => \$errors === [] ? 'correcta' : 'incorrecta'",
            "'filas_correctas' => \$correct",
            "'filas_incorrectas' => \$incorrect",
            "'PREVISUALIZACION_VALORES'",
        ] as $expected) {
            self::assertStringContainsString($expected, $preview);
        }

        self::assertStringNotContainsString('ValorActivo::create(', $preview);
        self::assertStringNotContainsString('->update($payload)', $preview);

        foreach ([
            "->where('estatus', 'correcta')",
            "'confirmar_aplicacion'",
            'ValorActivo::create($payload)',
            '$existing->update($payload)',
            "'estado' => 'aplicada'",
            "'APLICACION_IMPORTACION_VALORES'",
        ] as $expected) {
            self::assertStringContainsString($expected, $service . $this->read('app/Http/Controllers/ValoresActivoController.php'));
        }

        self::assertStringContainsString('DB::transaction(function () use ($batch, $userId): array', $apply);
    }

    public function test_preview_is_owned_expires_and_revalidates_before_apply(): void
    {
        $service = $this->read('app/Services/ValoresActivoImportService.php');

        foreach ([
            "->where('user_id', auth()->id())",
            "'expira_at' => now()->addHours",
            '$locked->expira_at?->isPast()',
            '$this->baselineMatches(',
            'cambiaron después de la previsualización',
            'dejó de cumplir las reglas vigentes',
        ] as $expected) {
            self::assertStringContainsString(
                $expected,
                $service . $this->read('app/Http/Controllers/ValoresActivoController.php')
            );
        }
    }

    public function test_routes_and_middleware_keep_management_profiles_only(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $permissions = $this->read('database/migrations/2026_07_13_000400_sync_swafi_role_permissions.php');

        foreach ([
            "->name('valores.importar')",
            "->name('valores.importaciones.aplicar')",
            "->name('valores.importaciones.cancelar')",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        foreach ([
            "'valores.importar'",
            "'valores.importaciones.aplicar'",
            "'valores.importaciones.cancelar'",
            "=> 'valores.administrar'",
        ] as $expected) {
            self::assertStringContainsString($expected, $middleware);
        }

        $captureBlock = $this->roleBlock($permissions, 'Usuario Captura');
        $auditBlock = $this->roleBlock($permissions, 'Usuario Consulta / Auditoría');
        $plantBlock = $this->roleBlock($permissions, 'Usuario Planta / Inventarios');

        self::assertStringContainsString("'valores.administrar'", $captureBlock);
        self::assertStringNotContainsString("'valores.administrar'", $auditBlock);
        self::assertStringNotContainsString("'valores.administrar'", $plantBlock);
        self::assertStringContainsString("'valores.ver'", $auditBlock);
        self::assertStringContainsString("'valores.ver'", $plantBlock);
    }

    public function test_interface_reports_correct_and_incorrect_rows_before_confirmation(): void
    {
        $view = $this->read('resources/views/swafi/valores.blade.php');

        foreach ([
            'Previsualizar CSV',
            'Registros correctos',
            'Registros incorrectos',
            'La previsualización no modifica datos.',
            'Confirmar y aplicar',
            'confirmar_aplicacion',
            'Cancelar previsualización',
            'Solo las filas correctas se aplican después de una confirmación expresa.',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
    }

    public function test_preview_uses_compact_grouped_layout_and_controlled_confirmation(): void
    {
        $view = $this->read('resources/views/swafi/valores.blade.php');

        foreach ([
            'vf-preview-meta-grid',
            'vf-preview-table',
            'Valores oficiales Oracle ERP',
            'vf-preview-values',
            'vf-preview-parameters',
            'vf-preview-decision',
            'vf-preview-confirmation-check',
            'width: 20px;',
            'Aplicación controlada del lote',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('min-width: 1700px;', $view);
        self::assertStringNotContainsString('<th>Depreciación acumulada</th>', $view);
    }

    public function test_preview_keeps_oracle_as_source_without_recalculating_values(): void
    {
        $service = $this->read('app/Services/ValoresActivoImportService.php');
        $view = $this->read('resources/views/swafi/valores.blade.php');

        foreach ([
            "'depreciacion_acumulada' => self::toDecimal",
            "'valor_en_libros' => self::toDecimal",
            "'vida_util_meses' => self::toInteger",
            'SWAFI no los calcula ni los compara entre sí.',
        ] as $expected) {
            self::assertStringContainsString($expected, $service . $view);
        }

        foreach ([
            'valor_fiscal - depreciacion_acumulada',
            'valor_fiscal - $payload',
            'DepreciacionReferencialService',
            'linea_recta',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $service);
        }
    }

    private function roleBlock(string $contents, string $role): string
    {
        $start = strpos($contents, "'{$role}' => [");
        self::assertNotFalse($start, "No se encontró el rol {$role}.");
        $end = strpos($contents, "            ],", (int) $start);
        self::assertNotFalse($end, "No se encontró el cierre del rol {$role}.");

        return substr($contents, (int) $start, (int) $end - (int) $start);
    }

    private function section(string $contents, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($contents, $startNeedle);
        $end = strpos($contents, $endNeedle, $start === false ? 0 : $start + strlen($startNeedle));

        self::assertNotFalse($start, "No se encontró {$startNeedle}.");
        self::assertNotFalse($end, "No se encontró {$endNeedle}.");

        return substr($contents, (int) $start, (int) $end - (int) $start);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, "No fue posible leer {$path}.");

        return $contents;
    }
}
