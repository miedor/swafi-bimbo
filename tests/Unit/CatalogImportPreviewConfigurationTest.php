<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogImportPreviewConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_incremental_migration_creates_traceable_batch_and_row_tables(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_19_000530_create_catalog_import_preview_tables.php'
        );

        foreach ([
            "Schema::create('importaciones_catalogo'",
            "Schema::create('importacion_catalogo_filas'",
            "\$table->uuid('uuid')->unique()",
            "\$table->char('archivo_hash_sha256', 64)",
            "\$table->json('resumen')->nullable()",
            "\$table->json('datos')",
            "\$table->json('errores')->nullable()",
            "\$table->json('advertencias')->nullable()",
            "\$table->boolean('aplicada')->default(false)",
            'public function down(): void',
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_migration_protects_ownership_history_and_frequent_queries(): void
    {
        $migration = $this->read(
            'database/migrations/2026_07_19_000530_create_catalog_import_preview_tables.php'
        );

        foreach ([
            "->constrained('users')->nullOnDelete()",
            "->constrained('importaciones_catalogo')",
            '->restrictOnDelete()',
            'import_cat_usuario_estado_idx',
            'import_cat_catalogo_fecha_idx',
            'import_cat_estado_expira_idx',
            'import_cat_fila_unica',
            'import_cat_fila_estatus_idx',
            'import_cat_fila_aplicada_idx',
            "Schema::dropIfExists('importacion_catalogo_filas')",
            "Schema::dropIfExists('importaciones_catalogo')",
        ] as $expected) {
            self::assertStringContainsString($expected, $migration);
        }
    }

    public function test_models_define_relations_json_casts_and_application_guard(): void
    {
        $batch = $this->read('app/Models/ImportacionCatalogo.php');
        $row = $this->read('app/Models/ImportacionCatalogoFila.php');
        $combined = $batch . "\n" . $row;

        foreach ([
            "protected \$table = 'importaciones_catalogo'",
            "protected \$table = 'importacion_catalogo_filas'",
            "'resumen' => 'array'",
            "'datos' => 'array'",
            "'errores' => 'array'",
            "'advertencias' => 'array'",
            "'aplicada' => 'boolean'",
            'public function usuario(): BelongsTo',
            'public function filas(): HasMany',
            'public function importacion(): BelongsTo',
            'public function puedeAplicarse(): bool',
            "\$this->estado === 'previsualizada'",
            '->isFuture()',
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_requests_validate_file_type_size_catalog_and_explicit_confirmation(): void
    {
        $import = $this->read('app/Http/Requests/ImportCatalogRequest.php');
        $apply = $this->read('app/Http/Requests/ApplyCatalogImportRequest.php');
        $index = $this->read('app/Http/Requests/CatalogIndexRequest.php');

        foreach ([
            "'mimes:csv,txt,xlsx'",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            "'max:10240'",
            'Rule::in(array_keys(CatalogManagementService::CATALOGS))',
            "'confirmar_aplicacion' => ['required', 'accepted']",
            "'lote' => ['nullable', 'uuid']",
            "Rule::in(['aceptada', 'observada', 'rechazada'])",
        ] as $expected) {
            self::assertStringContainsString($expected, $import . "\n" . $apply . "\n" . $index);
        }
    }

    public function test_xlsx_reader_rejects_unsafe_archives_formulas_and_external_xml_entities(): void
    {
        $reader = $this->read('app/Services/SimpleXlsxReader.php');

        foreach ([
            'class_exists(DOMDocument::class)',
            'class_exists(ZipArchive::class)',
            'MAX_UNCOMPRESSED_BYTES',
            "str_contains(\$name, '../')",
            "preg_match('/^[A-Za-z]:\\//', \$name)",
            './*[local-name()="f"]',
            'El archivo XLSX contiene fórmulas.',
            "str_contains(\$upper, '<!DOCTYPE')",
            "str_contains(\$upper, '<!ENTITY')",
            'LIBXML_NONET',
            'MAX_COLUMNS',
        ] as $expected) {
            self::assertStringContainsString($expected, $reader);
        }
    }

    public function test_preview_classifies_rows_without_modifying_business_catalogs(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');
        $preview = $this->section($service, 'public function preview(', 'public function apply(');

        foreach ([
            "'estado' => 'previsualizada'",
            "'aceptada' => \$summary['aceptados']++",
            "'observada' => \$summary['observados']++",
            "\$summary['rechazados']++",
            "ImportacionCatalogoFila::query()->create",
            "'IMPORTACION_CATALOGO_PREVIA'",
            'archivo_hash_sha256',
        ] as $expected) {
            self::assertStringContainsString($expected, $preview);
        }

        self::assertStringNotContainsString('catalogManagement->save(', $preview);
        self::assertStringNotContainsString("DB::table('plantas')->insert", $preview);
    }

    public function test_application_is_transactional_locked_revalidated_and_uses_existing_catalog_rules(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');
        $apply = $this->section($service, 'public function apply(', 'public function cancel(');

        foreach ([
            'DB::transaction(',
            '->lockForUpdate()',
            "->whereIn('estatus', self::VALID_STATUSES)",
            "->where('aplicada', false)",
            'assertDataIsStillValid(',
            'El catálogo cambió después de la previsualización.',
            '$this->catalogManagement->save(',
            "'estado' => 'aplicada'",
            "'IMPORTACION_CATALOGO_APLICADA'",
        ] as $expected) {
            self::assertStringContainsString($expected, $apply);
        }
    }

    public function test_preview_is_restricted_to_its_owner_and_expires_before_application(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');

        foreach ([
            "(int) \$locked->user_id !== \$userId",
            "->where('user_id', \$userId)",
            'markExpiredIfNeeded($locked)',
            "\$batch->estado === 'previsualizada'",
            "['estado' => 'expirada']",
            'La previsualización solicitada no existe o no pertenece a tu usuario.',
            'La previsualización ya fue aplicada, cancelada o venció.',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_routes_and_middleware_protect_preview_application_cancellation_and_exports(): void
    {
        $routes = $this->read('routes/web.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $combined = $routes . "\n" . $middleware;

        foreach ([
            "->name('catalogos.importaciones.aplicar')",
            "->name('catalogos.importaciones.cancelar')",
            "->name('catalogos.importaciones.incidencias-xlsx')",
            "->name('catalogos.importaciones.incidencias-csv')",
            "->whereUuid('lote')",
            "'catalogos.importaciones.aplicar'",
            "'catalogos.importaciones.cancelar'",
            "'catalogos.importaciones.incidencias-xlsx'",
            "'catalogos.importaciones.incidencias-csv'",
            "'catalogos.activate' => 'catalogos.administrar'",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_interface_requires_preview_and_explicit_confirmation_before_applying(): void
    {
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            'Carga masiva con previsualización',
            'Ningún catálogo se modifica hasta que revises el resultado',
            'Previsualizar y validar',
            'accept=".csv,.txt,.xlsx"',
            'Previsualización del layout',
            'Aceptadas',
            'Observadas',
            'Rechazadas',
            'name="confirmar_aplicacion"',
            'Aplicar carga validada',
            'Las filas rechazadas no modificarán los catálogos.',
            'data-swafi-query-workspace',
            '@media (max-width: 760px)',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('{!! $message !!}', $view);
    }

    public function test_incident_exports_are_available_in_xlsx_and_csv_with_formula_protection(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $service = $this->read('app/Services/CatalogImportService.php');
        $combined = $controller . "\n" . $service;

        foreach ([
            'public function exportarIncidenciasXlsx',
            'public function exportarIncidenciasCsv',
            'exportBytes(',
            'incidentRows(',
            'incidentHeaders()',
            'incidentDataRows(',
            "['=', '+', '-', '@']",
            "fputcsv(\$output, \$headers, ',', '\"', '')",
            "'EXPORTA_INCIDENCIAS_CATALOGO'",
            "'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0'",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_manual_and_bulk_catalog_changes_share_the_same_validation_service(): void
    {
        $request = $this->read('app/Http/Requests/StoreCatalogRequest.php');
        $validation = $this->read('app/Services/CatalogValidationService.php');
        $import = $this->read('app/Services/CatalogImportService.php');
        $combined = $request . "\n" . $validation . "\n" . $import;

        foreach ([
            'CatalogValidationService::class',
            'public function rules(',
            'public function messages(',
            'public function attributes(',
            'public function makeValidator(',
            '$this->catalogValidation->makeValidator(',
            "Rule::exists('plantas', 'id')->where",
            "Rule::unique('tipos_activo', 'descripcion')->ignore",
        ] as $expected) {
            self::assertStringContainsString($expected, $combined);
        }
    }

    public function test_import_limits_are_configurable_without_storing_secrets(): void
    {
        $config = $this->read('config/swafi.php');
        $environment = $this->read('.env.example');

        foreach ([
            "'importacion_max_filas'",
            "env('SWAFI_CATALOG_IMPORT_MAX_ROWS', 5000)",
            "'previsualizacion_horas'",
            "env('SWAFI_CATALOG_IMPORT_PREVIEW_HOURS', 24)",
            'SWAFI_CATALOG_IMPORT_MAX_ROWS=5000',
            'SWAFI_CATALOG_IMPORT_PREVIEW_HOURS=24',
        ] as $expected) {
            self::assertStringContainsString($expected, $config . "\n" . $environment);
        }

        self::assertStringNotContainsString('SWAFI_CATALOG_IMPORT_PASSWORD', $environment);
    }

    public function test_new_audit_actions_fit_the_existing_database_column(): void
    {
        foreach ([
            'IMPORTACION_CATALOGO_PREVIA',
            'IMPORTACION_CATALOGO_APLICADA',
            'IMPORTACION_CATALOGO_CANCELADA',
            'EXPORTA_INCIDENCIAS_CATALOGO',
        ] as $action) {
            self::assertLessThanOrEqual(40, strlen($action), $action . ' supera VARCHAR(40).');
        }
    }

    public function test_sessions_captcha_logical_deletion_and_query_experience_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $catalogView = $this->read('resources/views/swafi/catalogos.blade.php');
        $logicalDeletion = $this->read('tests/Unit/LogicalDeletionConfigurationTest.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString('<header class="swafi-page-header">', $layout);
        self::assertStringContainsString('nav-item-logout', $layout);
        self::assertStringContainsString('data-swafi-query-workspace', $catalogView);
        self::assertStringContainsString('data-swafi-query-results', $catalogView);
        self::assertStringContainsString('SoftDeletes', $logicalDeletion);
    }

    private function section(string $contents, string $start, string $end): string
    {
        $startPosition = strpos($contents, $start);
        $endPosition = strpos($contents, $end, $startPosition === false ? 0 : $startPosition + strlen($start));

        self::assertNotFalse($startPosition, 'No se encontró el inicio de la sección: ' . $start);
        self::assertNotFalse($endPosition, 'No se encontró el final de la sección: ' . $end);

        return substr($contents, (int) $startPosition, (int) $endPosition - (int) $startPosition);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);

        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}
