<?php

namespace Tests\Unit;

use App\Services\CatalogImportService;
use App\Services\CatalogManagementService;
use App\Services\CatalogValidationService;
use App\Services\SimpleXlsxReader;
use PHPUnit\Framework\TestCase;

class CatalogTemplateAndPreviewIntegrityConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_every_template_has_matching_headers_example_row_and_field_guide(): void
    {
        $service = new CatalogImportService(
            new CatalogManagementService(),
            new CatalogValidationService(),
            new SimpleXlsxReader()
        );

        foreach (array_keys(CatalogManagementService::CATALOGS) as $catalog) {
            $headers = $service->headersFor($catalog);
            $required = $service->requiredHeadersFor($catalog);
            $optional = $service->optionalHeadersFor($catalog);
            $guide = $service->fieldGuideFor($catalog);
            $example = $service->exampleRowFor($catalog);

            self::assertNotEmpty($headers, "El catálogo {$catalog} debe definir encabezados.");
            self::assertSame(
                count($headers),
                count($example),
                "La fila de ejemplo de {$catalog} debe coincidir con el número de encabezados."
            );
            self::assertSame(
                $headers,
                array_keys($guide),
                "La guía de campos de {$catalog} debe conservar exactamente el orden de la plantilla."
            );
            self::assertEmpty(
                array_diff($required, $headers),
                "Todos los encabezados requeridos de {$catalog} deben existir en la plantilla."
            );
            self::assertEmpty(
                array_diff($headers, array_merge($required, $optional)),
                "Cada encabezado de {$catalog} debe clasificarse como requerido u opcional."
            );
        }

        self::assertSame('area_clave', $service->headersFor('ubicaciones')[1]);
        self::assertContains('area_nombre', $service->acceptedHeadersFor('ubicaciones'));
    }

    public function test_all_m04_catalogs_define_exact_headers_required_fields_and_guidance(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');

        foreach ([
            "'proveedores' => ['rfc', 'nombre', 'correo', 'telefono', 'estatus']",
            "'plantas' => ['clave', 'nombre', 'direccion', 'estado', 'pais', 'estatus']",
            "'centros_costo' => ['planta_clave', 'clave', 'descripcion', 'estatus']",
            "'categorias_activo' => ['clave', 'nombre', 'descripcion', 'estatus']",
            "'tipos_activo' => ['categoria_clave', 'clave', 'descripcion', 'vida_util_meses', 'estatus']",
            "'estatus_documentales', 'estatus_operativos' => ['clave', 'nombre', 'descripcion', 'orden', 'estatus']",
            "'areas' => ['planta_clave', 'clave', 'nombre', 'estatus']",
            "'responsables' => ['nombre', 'correo', 'telefono', 'estatus']",
            "'area_clave'",
            'public function fieldGuideFor(string $catalog): array',
            'public function optionalHeadersFor(string $catalog): array',
            'SWAFI no calcula depreciación.',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_location_template_uses_stable_area_key_and_preserves_legacy_name_compatibility(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');

        foreach ([
            'public function acceptedHeadersFor(string $catalog): array',
            '$headers[] = \'area_nombre\';',
            '$areaKey = mb_strtoupper($this->normalizeCell($data[\'area_clave\'] ?? \'\'));',
            "->where('clave', \$areaKey)",
            '$areaName = $this->normalizeCell($data[\'area_nombre\'] ?? \'\');',
            "->where('nombre', \$areaName)",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_preview_rejects_unknown_or_duplicate_columns_and_keeps_original_values(): void
    {
        $service = $this->read('app/Services/CatalogImportService.php');

        foreach ([
            '$allowed = $this->acceptedHeadersFor($catalog);',
            'El layout contiene encabezados no reconocidos:',
            'Descarga la plantilla vigente del catálogo y conserva exactamente sus columnas.',
            'El layout contiene encabezados duplicados:',
            "'datos_origen' => \$sourceData",
            '$previousResult = is_array($row->resultado) ? $row->resultado : [];',
            "'resultado' => array_merge(\$previousResult, [",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function test_templates_are_downloadable_as_exact_csv_and_generated_xlsx(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $request = $this->read('app/Http/Requests/CatalogIndexRequest.php');

        foreach ([
            "'template_format' => ['nullable', Rule::in(['csv', 'xlsx'])]",
            '$this->route()?->getName() === \'catalogos.plantilla\'',
            '$format = (string) ($request->validated(\'template_format\') ?? \'csv\');',
            "if (\$format === 'xlsx')",
            '$this->xlsxExporter->exportBytes(',
            "fputcsv(\$output, \$headers, ',', '\"', '');",
            "'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'",
            "'Content-Type' => 'text/csv; charset=UTF-8'",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller . "\n" . $request);
        }
    }

    public function test_interface_exposes_required_optional_rules_and_full_row_preview(): void
    {
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            'Columnas exactas de la plantilla vigente',
            '$requiredHeadersLayout',
            '$optionalHeadersLayout',
            '$layoutFieldGuide',
            'Plantilla Excel',
            'Plantilla CSV',
            'Datos del layout',
            '$rowResult[\'datos_origen\']',
            '$rowPreviewValues',
            'Área (plantilla anterior)',
            'No agregues, renombres ni dupliques columnas.',
        ] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        self::assertStringNotContainsString('{!! $previewValue', $view);
    }

    public function test_profile_boundaries_remain_admin_and_capture_for_mutations(): void
    {
        $controller = $this->read('app/Http/Controllers/CatalogosController.php');
        $middleware = $this->read('app/Http/Middleware/SwafiAuth.php');
        $captureMigration = $this->read(
            'database/migrations/2026_07_21_000620_grant_catalog_administration_to_capture_role.php'
        );
        $view = $this->read('resources/views/swafi/catalogos.blade.php');

        foreach ([
            "'catalogos.plantilla'",
            "'catalogos.activate' => 'catalogos.administrar'",
            "private const ROLE_NAME = 'Usuario Captura';",
            "private const PERMISSION = 'catalogos.administrar';",
            'La administración está autorizada para <strong>Administrador SWAFI</strong> y <strong>Usuario Captura</strong>.',
            'Tu perfil cuenta con acceso de consulta.',
            '$this->catalogVisibility->canAdminister($request)',
        ] as $expected) {
            self::assertStringContainsString(
                $expected,
                $controller . "\n" . $middleware . "\n" . $captureMigration . "\n" . $view
            );
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . ltrim($relativePath, '/'));

        self::assertIsString($contents, 'No fue posible leer ' . $relativePath);

        return $contents;
    }
}
