<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyCatalogImportRequest;
use App\Http\Requests\CatalogIndexRequest;
use App\Http\Requests\ImportCatalogRequest;
use App\Http\Requests\StoreCatalogRequest;
use App\Http\Requests\UpdateCatalogStatusRequest;
use App\Services\CatalogImportService;
use App\Services\CatalogManagementService;
use App\Services\SimpleXlsxExporter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CatalogosController extends Controller
{
    public function __construct(
        private readonly CatalogManagementService $catalogManagement,
        private readonly CatalogImportService $catalogImports,
        private readonly SimpleXlsxExporter $xlsxExporter
    ) {
    }
    public function index(CatalogIndexRequest $request)
    {
        $validated = $request->validated();
        $catalogoActivo = (string) ($validated['catalogo'] ?? 'proveedores');
        $canAdminCatalogs = $this->canAdministerCatalogs($request);
        $importBatch = null;
        $importRows = null;

        if ($canAdminCatalogs && !empty($validated['lote'])) {
            try {
                $importBatch = $this->catalogImports->findOwnedBatch(
                    (string) $validated['lote'],
                    (int) auth()->id()
                );

                if ($importBatch->catalogo !== $catalogoActivo) {
                    return redirect()->route('catalogos', [
                        'catalogo' => $importBatch->catalogo,
                        'lote' => $importBatch->uuid,
                    ]);
                }

                $importRows = $importBatch->filas()
                    ->when(
                        !empty($validated['import_status']),
                        fn ($rowQuery) => $rowQuery->where('estatus', $validated['import_status'])
                    )
                    ->orderBy('numero_fila')
                    ->paginate(20, ['*'], 'import_page')
                    ->withQueryString();
            } catch (DomainException $exception) {
                return redirect()
                    ->route('catalogos', ['catalogo' => $catalogoActivo])
                    ->withErrors(['importacion' => $exception->getMessage()]);
            }
        }

        $query = $this->baseQuery($catalogoActivo);

        $this->applyFilters($query, $request, $catalogoActivo);

        if (($validated['export'] ?? null) === 'csv') {
            return $this->exportCsv($query, $catalogoActivo);
        }

        $this->applyOrder($query, $catalogoActivo);

        $resultados = $query
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        $registroEdit = $canAdminCatalogs
            ? $this->findForEdit($catalogoActivo, isset($validated['editar']) ? (string) $validated['editar'] : null)
            : null;

        $registroDetail = $this->findForDetail(
            $catalogoActivo,
            isset($validated['detalle']) ? (string) $validated['detalle'] : null
        );

        $dependenciasCatalogo = $registroDetail !== null
            ? $this->catalogManagement->dependenciesFor($catalogoActivo, (int) $registroDetail->id)
            : [];

        $dependenciasPlanta = $catalogoActivo === 'plantas'
            ? $dependenciasCatalogo
            : [];

        return view('swafi.catalogos', [
            'catalogoActivo' => $catalogoActivo,
            'catalogosDisponibles' => $this->catalogs(),
            'columnas' => $this->columnsFor($catalogoActivo),
            'resultados' => $resultados,
            'registroEdit' => $registroEdit,
            'registroDetail' => $registroDetail,
            'dependenciasPlanta' => $dependenciasPlanta,
            'dependenciasCatalogo' => $dependenciasCatalogo,
            'canAdminCatalogs' => $canAdminCatalogs,
            'filtros' => $validated,
            'opciones' => $this->options(),
            'kpis' => $this->buildKpis($catalogoActivo),
            'headersLayout' => $this->catalogImports->headersFor($catalogoActivo),
            'importBatch' => $importBatch,
            'importRows' => $importRows,
        ]);
    }

    public function store(StoreCatalogRequest $request)
    {
        try {
            $this->catalogManagement->save(
                catalog: $request->catalog(),
                data: $request->catalogData(),
                recordId: $request->recordId(),
                userId: auth()->id(),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors(['catalogo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('catalogos', ['catalogo' => $request->catalog()])
            ->with(
                'success',
                $request->recordId() !== null
                    ? 'El catálogo se actualizó correctamente.'
                    : 'El registro se creó correctamente.'
            );
    }

    public function importar(ImportCatalogRequest $request): RedirectResponse
    {
        $catalog = (string) $request->validated('catalogo');
        $file = $request->file('archivo_csv');

        try {
            $batch = $this->catalogImports->preview(
                file: $file,
                catalog: $catalog,
                userId: auth()->id(),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors(['archivo_csv' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'archivo_csv' => 'No fue posible previsualizar el layout. Revisa el archivo e inténtalo nuevamente.',
                ]);
        }

        return redirect()
            ->route('catalogos', [
                'catalogo' => $catalog,
                'lote' => $batch->uuid,
            ])
            ->with('success', 'El layout fue validado. Revisa la previsualización antes de aplicar los cambios.');
    }

    public function aplicarImportacion(
        ApplyCatalogImportRequest $request,
        string $lote
    ): RedirectResponse {
        try {
            $batch = $this->catalogImports->findOwnedBatch($lote, (int) auth()->id());

            if ($batch->catalogo !== (string) $request->validated('catalogo')) {
                throw new DomainException('El catálogo enviado no coincide con la previsualización seleccionada.');
            }

            $summary = $this->catalogImports->apply(
                batch: $batch,
                userId: (int) auth()->id(),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('catalogos', ['catalogo' => $request->validated('catalogo'), 'lote' => $lote])
                ->withInput()
                ->withErrors(['importacion' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('catalogos', ['catalogo' => $request->validated('catalogo'), 'lote' => $lote])
                ->withErrors([
                    'importacion' => 'No fue posible aplicar el lote. No se confirmó ningún cambio parcial.',
                ]);
        }

        return redirect()
            ->route('catalogos', ['catalogo' => $batch->catalogo, 'lote' => $batch->uuid])
            ->with('success', 'La carga de catálogos fue aplicada correctamente.')
            ->with('import_apply_summary', $summary);
    }

    public function cancelarImportacion(Request $request, string $lote): RedirectResponse
    {
        try {
            $batch = $this->catalogImports->findOwnedBatch($lote, (int) auth()->id());
            $this->catalogImports->cancel($batch, (int) auth()->id(), $request->ip());
        } catch (DomainException $exception) {
            return back()->withErrors(['importacion' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'importacion' => 'No fue posible cancelar la previsualización. Inténtalo nuevamente.',
            ]);
        }

        return redirect()
            ->route('catalogos', ['catalogo' => $batch->catalogo])
            ->with('success', 'La previsualización fue cancelada sin modificar los catálogos.');
    }

    public function exportarIncidenciasXlsx(Request $request, string $lote): RedirectResponse|StreamedResponse
    {
        try {
            $batch = $this->catalogImports->findOwnedBatch($lote, (int) auth()->id());
            $rows = $this->catalogImports->incidentRows($batch);

            if ($rows->isEmpty()) {
                throw new DomainException('El lote no contiene filas observadas o rechazadas para exportar.');
            }

            $contents = $this->xlsxExporter->exportBytes(
                'Incidencias catálogos',
                $this->catalogImports->incidentHeaders(),
                $this->catalogImports->incidentDataRows($rows, $batch->catalogo)
            );

            $this->catalogImports->registerIncidentExport(
                $batch,
                (int) auth()->id(),
                $request->ip(),
                'xlsx',
                $rows->count()
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['importacion' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'importacion' => 'No fue posible generar el Excel de incidencias. Utiliza la descarga CSV disponible.',
            ]);
        }

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'incidencias_catalogos_' . $batch->uuid . '.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function exportarIncidenciasCsv(Request $request, string $lote): RedirectResponse|StreamedResponse
    {
        try {
            $batch = $this->catalogImports->findOwnedBatch($lote, (int) auth()->id());
            $rows = $this->catalogImports->incidentRows($batch);

            if ($rows->isEmpty()) {
                throw new DomainException('El lote no contiene filas observadas o rechazadas para exportar.');
            }

            $headers = $this->catalogImports->incidentHeaders();
            $dataRows = $this->catalogImports->incidentDataRows($rows, $batch->catalogo);

            $this->catalogImports->registerIncidentExport(
                $batch,
                (int) auth()->id(),
                $request->ip(),
                'csv',
                $rows->count()
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['importacion' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'importacion' => 'No fue posible preparar el CSV de incidencias.',
            ]);
        }

        return response()->streamDownload(
            static function () use ($headers, $dataRows): void {
                $output = fopen('php://output', 'wb');

                if (!is_resource($output)) {
                    throw new \RuntimeException('No fue posible iniciar la descarga CSV.');
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers, ',', '"', '');

                foreach ($dataRows as $row) {
                    fputcsv($output, array_map(
                        static fn (mixed $value): string => self::safeSpreadsheetValue($value),
                        $row
                    ), ',', '"', '');
                }

                fclose($output);
            },
            'incidencias_catalogos_' . $batch->uuid . '.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function plantillaCsv(CatalogIndexRequest $request): StreamedResponse
    {
        $catalog = (string) ($request->validated('catalogo') ?? 'proveedores');
        $headers = $this->catalogImports->headersFor($catalog);
        $example = $this->catalogImports->exampleRowFor($catalog);

        return response()->streamDownload(function () use ($headers, $example): void {
            $output = fopen('php://output', 'wb');

            if (!is_resource($output)) {
                throw new \RuntimeException('No fue posible iniciar la descarga de la plantilla.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv(
                $output,
                array_map(fn ($header) => Str::headline(str_replace('_', ' ', $header)), $headers),
                ',',
                '"',
                ''
            );
            fputcsv($output, $example, ',', '"', '');
            fclose($output);
        }, 'plantilla_catalogo_swafi_' . $catalog . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(UpdateCatalogStatusRequest $request, string $catalogo, int $id)
    {
        return $this->updateStatus($request, 'inactivo');
    }

    public function activate(UpdateCatalogStatusRequest $request, string $catalogo, int $id)
    {
        return $this->updateStatus($request, 'activo');
    }

    private function updateStatus(UpdateCatalogStatusRequest $request, string $status)
    {
        try {
            $this->catalogManagement->changeStatus(
                catalog: $request->catalog(),
                recordId: $request->recordId(),
                status: $status,
                userId: auth()->id(),
                ip: $request->ip()
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('catalogos', [
                    'catalogo' => $request->catalog(),
                    'detalle' => $request->recordId(),
                ])
                ->withErrors(['catalogo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('catalogos', ['catalogo' => $request->catalog()])
            ->with(
                'success',
                $status === 'activo'
                    ? 'El registro fue activado correctamente.'
                    : 'El registro fue desactivado correctamente. Se conserva por trazabilidad.'
            );
    }

    private function catalogs(): array
    {
        return collect(CatalogManagementService::CATALOGS)
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['label']])
            ->all();
    }


    private function tableFor(string $catalogo): string
    {
        return $this->catalogManagement->tableFor($catalogo);
    }

    private function baseQuery(string $catalogo)
    {
        return match ($catalogo) {
            'centros_costo' => DB::table('centros_costo as cc')
                ->leftJoin('plantas as p', 'p.id', '=', 'cc.planta_id')
                ->select([
                    'cc.id',
                    'cc.planta_id',
                    'p.clave as planta_clave',
                    'p.nombre as planta_nombre',
                    'cc.clave',
                    'cc.descripcion',
                    'cc.estatus',
                    'cc.created_at',
                    'cc.updated_at',
                ]),

            'tipos_activo' => DB::table('tipos_activo as ta')
                ->leftJoin('categorias_activo as ca', 'ca.id', '=', 'ta.categoria_activo_id')
                ->select([
                    'ta.id',
                    'ta.categoria_activo_id',
                    'ca.clave as categoria_clave',
                    'ca.nombre as categoria_nombre',
                    'ta.clave',
                    'ta.descripcion',
                    'ta.vida_util_meses',
                    'ta.estatus',
                    'ta.created_at',
                    'ta.updated_at',
                ]),

            'areas' => DB::table('areas as a')
                ->leftJoin('plantas as p', 'p.id', '=', 'a.planta_id')
                ->select([
                    'a.id',
                    'a.planta_id',
                    'p.clave as planta_clave',
                    'p.nombre as planta_nombre',
                    'a.clave',
                    'a.nombre',
                    'a.estatus',
                    'a.created_at',
                    'a.updated_at',
                ]),

            'ubicaciones' => DB::table('ubicaciones as u')
                ->leftJoin('plantas as p', 'p.id', '=', 'u.planta_id')
                ->leftJoin('areas as a', 'a.id', '=', 'u.area_id')
                ->select([
                    'u.id',
                    'u.planta_id',
                    'u.area_id',
                    'p.clave as planta_clave',
                    'p.nombre as planta_nombre',
                    'a.nombre as area_nombre',
                    'u.codigo_interno',
                    'u.edificio',
                    'u.piso',
                    'u.pasillo',
                    'u.descripcion',
                    'u.estatus',
                    'u.created_at',
                    'u.updated_at',
                ]),

            default => DB::table($this->tableFor($catalogo))->select('*'),
        };
    }

    private function applyFilters($query, Request $request, string $catalogo): void
    {
        if ($request->filled('buscar')) {
            $buscar = $this->likePattern((string) $request->input('buscar'));

            match ($catalogo) {
                'proveedores' => $query->where(function ($where) use ($buscar) {
                    $where->where('rfc', 'like', $buscar)
                        ->orWhere('nombre', 'like', $buscar)
                        ->orWhere('correo', 'like', $buscar);
                }),

                'plantas' => $query->where(function ($where) use ($buscar) {
                    $where->where('clave', 'like', $buscar)
                        ->orWhere('nombre', 'like', $buscar)
                        ->orWhere('estado', 'like', $buscar)
                        ->orWhere('pais', 'like', $buscar);
                }),

                'centros_costo' => $query->where(function ($where) use ($buscar) {
                    $where->where('cc.clave', 'like', $buscar)
                        ->orWhere('cc.descripcion', 'like', $buscar)
                        ->orWhere('p.clave', 'like', $buscar)
                        ->orWhere('p.nombre', 'like', $buscar);
                }),

                'categorias_activo' => $query->where(function ($where) use ($buscar) {
                    $where->where('clave', 'like', $buscar)
                        ->orWhere('nombre', 'like', $buscar)
                        ->orWhere('descripcion', 'like', $buscar);
                }),

                'tipos_activo' => $query->where(function ($where) use ($buscar) {
                    $where->where('ta.clave', 'like', $buscar)
                        ->orWhere('ta.descripcion', 'like', $buscar)
                        ->orWhere('ca.clave', 'like', $buscar)
                        ->orWhere('ca.nombre', 'like', $buscar);
                }),

                'estatus_documentales', 'estatus_operativos' => $query->where(function ($where) use ($buscar) {
                    $where->where('clave', 'like', $buscar)
                        ->orWhere('nombre', 'like', $buscar)
                        ->orWhere('descripcion', 'like', $buscar);
                }),

                'areas' => $query->where(function ($where) use ($buscar) {
                    $where->where('a.clave', 'like', $buscar)
                        ->orWhere('a.nombre', 'like', $buscar)
                        ->orWhere('p.clave', 'like', $buscar)
                        ->orWhere('p.nombre', 'like', $buscar);
                }),

                'ubicaciones' => $query->where(function ($where) use ($buscar) {
                    $where->where('u.codigo_interno', 'like', $buscar)
                        ->orWhere('u.descripcion', 'like', $buscar)
                        ->orWhere('u.edificio', 'like', $buscar)
                        ->orWhere('u.piso', 'like', $buscar)
                        ->orWhere('u.pasillo', 'like', $buscar)
                        ->orWhere('p.nombre', 'like', $buscar)
                        ->orWhere('a.nombre', 'like', $buscar);
                }),

                'responsables' => $query->where(function ($where) use ($buscar) {
                    $where->where('nombre', 'like', $buscar)
                        ->orWhere('correo', 'like', $buscar)
                        ->orWhere('telefono', 'like', $buscar);
                }),

                default => null,
            };
        }

        if ($request->filled('estatus')) {
            $estatus = $request->input('estatus');

            if ($catalogo === 'centros_costo') {
                $query->where('cc.estatus', $estatus);
            } elseif ($catalogo === 'tipos_activo') {
                $query->where('ta.estatus', $estatus);
            } elseif ($catalogo === 'areas') {
                $query->where('a.estatus', $estatus);
            } elseif ($catalogo === 'ubicaciones') {
                $query->where('u.estatus', $estatus);
            } else {
                $query->where('estatus', $estatus);
            }
        }

        if ($request->filled('planta_id') && in_array($catalogo, ['centros_costo', 'areas', 'ubicaciones'], true)) {
            if ($catalogo === 'centros_costo') {
                $query->where('cc.planta_id', $request->input('planta_id'));
            } elseif ($catalogo === 'areas') {
                $query->where('a.planta_id', $request->input('planta_id'));
            } else {
                $query->where('u.planta_id', $request->input('planta_id'));
            }
        }

        if ($request->filled('categoria_activo_id') && $catalogo === 'tipos_activo') {
            $query->where('ta.categoria_activo_id', $request->input('categoria_activo_id'));
        }

        if ($request->filled('area_id') && $catalogo === 'ubicaciones') {
            $query->where('u.area_id', $request->input('area_id'));
        }
    }

    private function applyOrder($query, string $catalogo): void
    {
        match ($catalogo) {
            'proveedores' => $query->orderBy('nombre'),
            'plantas' => $query->orderBy('nombre'),
            'centros_costo' => $query->orderBy('p.nombre')->orderBy('cc.clave'),
            'categorias_activo' => $query->orderBy('nombre'),
            'tipos_activo' => $query->orderBy('ca.nombre')->orderBy('ta.descripcion'),
            'estatus_documentales', 'estatus_operativos' => $query->orderBy('orden')->orderBy('nombre'),
            'areas' => $query->orderBy('p.nombre')->orderBy('a.clave')->orderBy('a.nombre'),
            'ubicaciones' => $query->orderBy('p.nombre')->orderBy('a.nombre')->orderBy('u.codigo_interno'),
            'responsables' => $query->orderBy('nombre'),
            default => $query->orderBy('id'),
        };
    }

    private function columnsFor(string $catalogo): array
    {
        return match ($catalogo) {
            'proveedores' => [
                'rfc' => 'RFC',
                'nombre' => 'Nombre',
                'correo' => 'Correo',
                'telefono' => 'Teléfono',
                'estatus' => 'Estatus',
            ],

            'plantas' => [
                'clave' => 'Clave',
                'nombre' => 'Nombre',
                'direccion' => 'Dirección',
                'estado' => 'Estado',
                'pais' => 'País',
                'estatus' => 'Estatus',
            ],

            'centros_costo' => [
                'planta_nombre' => 'Planta',
                'clave' => 'Clave',
                'descripcion' => 'Descripción',
                'estatus' => 'Estatus',
            ],

            'categorias_activo' => [
                'clave' => 'Clave',
                'nombre' => 'Categoría',
                'descripcion' => 'Descripción',
                'estatus' => 'Estatus',
            ],

            'tipos_activo' => [
                'categoria_nombre' => 'Categoría',
                'clave' => 'Clave',
                'descripcion' => 'Tipo de activo',
                'vida_util_meses' => 'Vida útil meses',
                'estatus' => 'Estatus',
            ],

            'estatus_documentales', 'estatus_operativos' => [
                'clave' => 'Clave técnica',
                'nombre' => 'Nombre visible',
                'descripcion' => 'Descripción',
                'orden' => 'Orden',
                'es_sistema' => 'Protegido',
                'estatus' => 'Estatus',
            ],

            'areas' => [
                'planta_nombre' => 'Planta',
                'clave' => 'Clave',
                'nombre' => 'Área',
                'estatus' => 'Estatus',
            ],

            'ubicaciones' => [
                'planta_nombre' => 'Planta',
                'area_nombre' => 'Área',
                'codigo_interno' => 'Código interno',
                'edificio' => 'Edificio',
                'piso' => 'Piso',
                'pasillo' => 'Pasillo',
                'descripcion' => 'Descripción',
                'estatus' => 'Estatus',
            ],

            'responsables' => [
                'nombre' => 'Nombre',
                'correo' => 'Correo',
                'telefono' => 'Teléfono',
                'estatus' => 'Estatus',
            ],

            default => [],
        };
    }

    private function findForEdit(string $catalogo, ?string $id)
    {
        if (!$id) {
            return null;
        }

        return DB::table($this->tableFor($catalogo))
            ->where('id', $id)
            ->first();
    }

    private function findForDetail(string $catalogo, ?string $id)
    {
        if (!$id) {
            return null;
        }

        return $this->baseQuery($catalogo)
            ->where($this->qualifiedIdColumn($catalogo), (int) $id)
            ->first();
    }

    private function qualifiedIdColumn(string $catalogo): string
    {
        return match ($catalogo) {
            'centros_costo' => 'cc.id',
            'tipos_activo' => 'ta.id',
            'areas' => 'a.id',
            'ubicaciones' => 'u.id',
            default => 'id',
        };
    }

    private function canAdministerCatalogs(Request $request): bool
    {
        $roles = collect($request->session()->get('swafi_roles', []));
        $permissions = collect($request->session()->get('swafi_permissions', []));

        return $roles->contains('Administrador SWAFI')
            || $permissions->contains('catalogos.administrar');
    }

    private function options(): array
    {
        return [
            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'categorias_activo' => DB::table('categorias_activo')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'areas' => DB::table('areas as a')
                ->join('plantas as p', 'p.id', '=', 'a.planta_id')
                ->where('a.estatus', 'activo')
                ->orderBy('p.nombre')
                ->orderBy('a.nombre')
                ->select([
                    'a.id',
                    'a.clave',
                    'a.nombre',
                    'a.planta_id',
                    'p.nombre as planta_nombre',
                ])
                ->get(),
        ];
    }

    private function buildKpis(string $catalogo): array
    {
        $table = $this->tableFor($catalogo);

        return [
            'total' => DB::table($table)->count(),
            'activos' => DB::table($table)->where('estatus', 'activo')->count(),
            'inactivos' => DB::table($table)->where('estatus', 'inactivo')->count(),
            'catalogo' => $this->catalogs()[$catalogo] ?? 'Catálogo',
        ];
    }

    private function exportCsv($query, string $catalogo)
    {
        $columns = $this->columnsFor($catalogo);
        $this->applyOrder($query, $catalogo);
        $rows = $query->cursor();

        return response()->streamDownload(function () use ($rows, $columns) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns), ',', '"', '');

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($columns) as $key) {
                    $line[] = $this->csvSafeValue(data_get($row, $key));
                }

                fputcsv($output, $line, ',', '"', '');
            }

            fclose($output);
        }, 'catalogo_swafi_' . $catalogo . '_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function likePattern(string $value): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            trim($value)
        );

        return '%' . $escaped . '%';
    }

    private function csvSafeValue(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $trimmed = ltrim($value);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    private static function safeSpreadsheetValue(mixed $value): string
    {
        $string = trim((string) $value);

        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@'], true)) {
            return "'" . $string;
        }

        return $string;
    }

}
