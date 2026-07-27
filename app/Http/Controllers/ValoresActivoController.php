<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterValoresActivoRequest;
use App\Http\Requests\ImportValoresActivoRequest;
use App\Http\Requests\StoreValorActivoRequest;
use App\Models\ImportacionValores;
use App\Models\ValorActivo;
use App\Services\CfdiValidationService;
use App\Services\FinancialCatalogService;
use App\Services\SafeExceptionReporter;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\SwafiAuthorizationService;
use App\Services\ValoresActivoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValoresActivoController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    /**
     * Esquema centralizado de la carga masiva de valores.
     *
     * Se conservan estas constantes en el controlador para mantener compatibilidad
     * con las pruebas de regresión existentes y evitar divergencias entre plantilla,
     * previsualización y aplicación.
     *
     * @var list<string>
     */
    private const IMPORT_REQUIRED_HEADERS = ValoresActivoImportService::REQUIRED_HEADERS;

    /** @var array<string, string> */
    private const IMPORT_TEMPLATE_HEADERS = ValoresActivoImportService::TEMPLATE_HEADERS;

    /** @var array<string, string> */
    private const IMPORT_HEADER_ALIASES = ValoresActivoImportService::HEADER_ALIASES;

    public function __construct(
        private readonly SwafiAuthorizationService $authorization,
        private readonly SafeExceptionReporter $safeExceptions,
        private readonly FinancialCatalogService $financialCatalogs,
        private readonly ValoresActivoImportService $valueImports,
        private readonly SimpleXlsxExporter $xlsxExporter,
        private readonly SimplePdfTableExporter $pdfExporter
    ) {
    }

    public function index(FilterValoresActivoRequest $request)
    {
        $filters = $request->validated();
        $canViewSensitiveValues = $this->canViewSensitiveValues();
        $query = $this->baseQuery($canViewSensitiveValues);
        $this->applyFilters($query, $request, $canViewSensitiveValues);

        $exportFormat = strtolower((string) $request->input('export'));

        if (in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            abort_unless(
                $this->canExportValues(),
                403,
                'No tienes permiso para exportar la consulta de valores.'
            );

            return $this->exportValues(
                query: $query,
                request: $request,
                format: $exportFormat,
                includeSensitiveValues: $canViewSensitiveValues
            );
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $resultados = $query
            ->orderByDesc('v.fecha_corte')
            ->orderByDesc('v.id')
            ->paginate($perPage)
            ->withQueryString();

        $canManageValues = $this->canManageValues();
        $valorEdit = null;
        $importBatch = null;
        $importRows = null;
        $previewStatus = (string) $request->input('preview_status', '');

        if ($request->filled('editar_valor') && $canManageValues) {
            $valorEdit = $this->findValorForEdit((int) $request->input('editar_valor'));
        }

        if ($request->filled('lote') && $canManageValues) {
            $importBatch = $this->findOwnedImportBatch((string) $request->input('lote'));
            $previewQuery = $importBatch->filas()->orderBy('numero_fila');

            if (in_array($previewStatus, ['correcta', 'incorrecta'], true)) {
                $previewQuery->where('estatus', $previewStatus);
            }

            $importRows = $previewQuery
                ->paginate(25, ['*'], 'preview_page')
                ->withQueryString();
        }

        return view('swafi.valores', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos($canViewSensitiveValues),
            'filtros' => $filters,
            'valorEdit' => $valorEdit,
            'importBatch' => $importBatch,
            'importRows' => $importRows,
            'previewStatus' => $previewStatus,
            'canAdministrarValores' => $canManageValues,
            'canViewSensitiveValues' => $canViewSensitiveValues,
            'canExportarValores' => $this->canExportValues(),
            'canExportarExcel' => $canViewSensitiveValues,
            'canExportarPdf' => $canViewSensitiveValues,
        ]);
    }

    public function store(StoreValorActivoRequest $request, CfdiValidationService $cfdiService)
    {
        $this->abortUnlessCanManageValues();
        $data = $request->validated();
        $existing = !empty($data['valor_id'])
            ? ValorActivo::withTrashed()->findOrFail((int) $data['valor_id'])
            : ValorActivo::withTrashed()->where('numero_activo', $data['numero_activo'])->first();

        if ($existing && empty($data['motivo_cambio'])) {
            return back()->withInput()->withErrors([
                'motivo_cambio' => 'Toda actualización debe indicar el motivo del cambio para conservar trazabilidad.',
            ]);
        }

        if ($existing && $existing->numero_activo !== $data['numero_activo']) {
            $duplicate = ValorActivo::withTrashed()->where('numero_activo', $data['numero_activo'])
                ->where('id', '<>', $existing->id)
                ->exists();

            if ($duplicate) {
                return back()->withInput()->withErrors([
                    'numero_activo' => 'El activo seleccionado ya cuenta con valores registrados.',
                ]);
            }
        }

        $currency = strtoupper((string) $data['moneda']);
        $requiresExchangeRate = $this->financialCatalogs->currencyRequiresExchangeRate($currency);
        $payload = [
            'numero_activo' => $data['numero_activo'],
            'valor_fiscal' => $data['valor_fiscal'],
            'valor_financiero' => $data['valor_financiero'],
            'moneda' => $currency,
            'tipo_cambio' => $requiresExchangeRate ? ($data['tipo_cambio'] ?? null) : 1,
            'fecha_tipo_cambio' => $requiresExchangeRate ? ($data['fecha_tipo_cambio'] ?? null) : null,
            'origen_tipo_cambio' => $requiresExchangeRate ? ($data['origen_tipo_cambio'] ?? null) : null,
            'depreciacion_acumulada' => $data['depreciacion_acumulada'],
            'valor_en_libros' => $data['valor_en_libros'],
            'vida_util_meses' => $data['vida_util_meses'] ?? null,
            'estatus_contable' => $data['estatus_contable'],
            'motivo_cambio' => $data['motivo_cambio'] ?: ($existing ? null : 'Registro inicial de valores.'),
            'fecha_corte' => $data['fecha_corte'],
            'registrado_por' => auth()->id(),
        ];

        $reconciliation = $cfdiService->reconcileValuePayload($data['numero_activo'], $payload);

        $payload['cfdi_validacion_id'] = $reconciliation['validation_id'];
        $payload['conciliacion_cfdi'] = $reconciliation['status'];
        $payload['conciliacion_detalle'] = $reconciliation['details'];

        DB::transaction(function () use ($existing, $payload): void {
            if ($existing) {
                $before = $existing->toArray();
                $wasDeleted = $existing->trashed();

                if ($wasDeleted) {
                    $existing->restore();
                }

                $existing->forceFill([
                    'deleted_by' => null,
                    'delete_reason' => null,
                ]);
                $existing->update($payload);
                $fresh = $existing->fresh();

                $this->registerAudit(
                    $fresh->numero_activo,
                    $wasDeleted ? 'RESTAURACION_VALOR' : 'EDICION_VALOR',
                    (string) $fresh->id,
                    $before,
                    $fresh->toArray()
                );

                return;
            }

            $value = ValorActivo::create($payload);
            $this->registerAudit($value->numero_activo, 'ALTA_VALOR', (string) $value->id, null, $value->toArray());
        });

        return redirect()
            ->route('valores')
            ->with('success', $existing
                ? 'Los valores oficiales provenientes de Oracle ERP se actualizaron correctamente.'
                : 'Los valores oficiales provenientes de Oracle ERP se registraron correctamente.');
    }

    public function importar(ImportValoresActivoRequest $request): RedirectResponse
    {
        $this->abortUnlessCanManageValues();

        try {
            $batch = $this->valueImports->previsualizar(
                file: $request->file('archivo_csv'),
                userId: (int) auth()->id()
            );

            return redirect()
                ->route('valores', [
                    'panel' => 'importar',
                    'lote' => $batch->uuid,
                ])
                ->with(
                    'success',
                    'La previsualización fue generada. Revisa las filas correctas e incorrectas antes de confirmar la aplicación.'
                );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_values_bulk_import',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores', ['panel' => 'importar'])
                ->withErrors([
                    'archivo_csv' => "La importación fue revertida. Referencia: {$reference}. No se modificaron valores.",
                ]);
        }
    }

    public function aplicarImportacion(Request $request, string $lote): RedirectResponse
    {
        $this->abortUnlessCanManageValues();
        $request->validate([
            'confirmar_aplicacion' => ['accepted'],
        ], [
            'confirmar_aplicacion.accepted' => 'Debes confirmar que revisaste la previsualización antes de aplicar los valores.',
        ]);
        $batch = $this->findOwnedImportBatch($lote);

        try {
            $summary = $this->valueImports->aplicar(
                batch: $batch,
                userId: (int) auth()->id()
            );

            return redirect()
                ->route('valores', [
                    'panel' => 'importar',
                    'lote' => $batch->uuid,
                ])
                ->with('success', 'La carga masiva de valores fue aplicada correctamente.')
                ->with('import_summary', $summary);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_values_bulk_apply',
                [
                    'batch_id' => $batch->id,
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores', [
                    'panel' => 'importar',
                    'lote' => $batch->uuid,
                ])
                ->withErrors([
                    'lote' => "La carga no fue aplicada. No se confirmó ningún cambio. Referencia: {$reference}.",
                ]);
        }
    }

    public function cancelarImportacion(string $lote): RedirectResponse
    {
        $this->abortUnlessCanManageValues();
        $batch = $this->findOwnedImportBatch($lote);

        try {
            $this->valueImports->cancelar(
                batch: $batch,
                userId: (int) auth()->id()
            );

            return redirect()
                ->route('valores', ['panel' => 'importar'])
                ->with('success', 'La previsualización fue cancelada sin modificar los valores fiscales y financieros.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_values_bulk_cancel',
                [
                    'batch_id' => $batch->id,
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores', [
                    'panel' => 'importar',
                    'lote' => $batch->uuid,
                ])
                ->withErrors([
                    'lote' => "No fue posible cancelar la previsualización. Referencia: {$reference}.",
                ]);
        }
    }

    public function plantillaCsv()
    {
        $this->abortUnlessCanManageValues();

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values(self::IMPORT_TEMPLATE_HEADERS));
            fputcsv($output, [
                'BIM-537028', '602700', '10045', '592655', '602700', 'MXN', '1', '', '',
                '60', '25/06/2027', 'vigente',
                'Carga de valores oficiales provenientes de Oracle ERP.',
            ]);
            fclose($output);
        }, 'plantilla_valores_activo_swafi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Request $request, int $valor)
    {
        $this->abortUnlessCanManageValues();

        $validated = $request->validate([
            'motivo_baja' => ['nullable', 'string', 'max:500'],
        ]);
        $motivoBaja = trim((string) ($validated['motivo_baja'] ?? ''))
            ?: 'Baja lógica solicitada desde el módulo de valores.';

        DB::transaction(function () use ($valor, $motivoBaja): void {
            $record = ValorActivo::query()->findOrFail($valor);
            $before = $record->toArray();
            $asset = $record->numero_activo;

            $record->forceFill([
                'deleted_by' => auth()->id(),
                'delete_reason' => $motivoBaja,
            ]);
            $record->save();
            $record->delete();

            $this->registerAudit(
                $asset,
                'BAJA_LOGICA_VALOR',
                (string) $valor,
                $before,
                ValorActivo::withTrashed()->find($valor)?->toArray() ?? [
                    'deleted_at' => $record->deleted_at,
                    'deleted_by' => auth()->id(),
                    'delete_reason' => $motivoBaja,
                ]
            );
        });

        return redirect()
            ->route('valores')
            ->with(
                'success',
                'Los valores fueron dados de baja lógicamente. Se conservan para auditoría y el Dashboard marcará el activo como pendiente.'
            );
    }

    private function baseQuery(bool $includeSensitiveValues = true)
    {
        $latestExpedientes = DB::table('expedientes')
            ->whereNull('deleted_at')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        $query = DB::table('valores_activo as v')
            ->whereNull('v.deleted_at')
            ->join('activos as a', 'a.numero_activo', '=', 'v.numero_activo')
            ->leftJoinSub($latestExpedientes, 'le', fn ($join) => $join->on('le.numero_activo', '=', 'a.numero_activo'))
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoin('cfdi_validaciones as cv', 'cv.id', '=', 'v.cfdi_validacion_id');

        $commonColumns = [
            'v.id as valor_id',
            'v.numero_activo',
            'v.estatus_contable',
            'v.conciliacion_cfdi',
            'v.fecha_corte',
            'v.updated_at',
            'e.id as expediente_id',
            'a.descripcion as activo_descripcion',
            'a.estatus_operativo',
            'a.estatus_documental',
            'cc.id as centro_costo_id',
            'cc.clave as centro_costo_clave',
            'pl.id as planta_id',
            'pl.nombre as planta_nombre',
            'ta.id as tipo_activo_id',
            'ta.descripcion as tipo_activo',
        ];

        if (!$includeSensitiveValues) {
            return $query->select($commonColumns);
        }

        return $query->select(array_merge($commonColumns, [
            'v.valor_fiscal',
            'v.valor_financiero',
            'v.moneda',
            'v.tipo_cambio',
            'v.fecha_tipo_cambio',
            'v.origen_tipo_cambio',
            'v.depreciacion_acumulada',
            'v.valor_en_libros',
            'v.vida_util_meses',
            'v.motivo_cambio',
            'v.conciliacion_detalle',
            'v.created_at',
            'e.folio_factura',
            'e.uuid_cfdi',
            'p.id as proveedor_id',
            'p.nombre as proveedor_nombre',
            'p.rfc as proveedor_rfc',
            'cv.estatus_validacion as cfdi_estatus',
            'cv.total as cfdi_total',
            'cv.moneda as cfdi_moneda',
            'cv.uuid_cfdi as cfdi_uuid',
        ]));
    }

    private function applyFilters(
        $query,
        Request $request,
        bool $includeSensitiveValues = true
    ): void {
        foreach ([
            'planta_id' => 'a.planta_id',
            'centro_costo_id' => 'a.centro_costo_id',
            'tipo_activo_id' => 'a.tipo_activo_id',
            'estatus_contable' => 'v.estatus_contable',
            'conciliacion_cfdi' => 'v.conciliacion_cfdi',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->filled('numero_activo')) {
            $query->where('v.numero_activo', 'like', '%' . $request->input('numero_activo') . '%');
        }

        if (!$includeSensitiveValues) {
            return;
        }

        foreach ([
            'proveedor_id' => 'a.proveedor_id',
            'moneda' => 'v.moneda',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('v.fecha_corte', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('v.fecha_corte', '<=', $request->input('fecha_hasta'));
        }
        if ($request->filled('valor_desde')) {
            $query->where('v.valor_fiscal', '>=', $request->input('valor_desde'));
        }
        if ($request->filled('valor_hasta')) {
            $query->where('v.valor_fiscal', '<=', $request->input('valor_hasta'));
        }
    }

    private function catalogos(bool $includeSensitiveValues = true): array
    {
        return [
            'activos' => DB::table('activos')->select('numero_activo', 'descripcion')->orderBy('numero_activo')->get(),
            'plantas' => DB::table('plantas')->where('estatus', 'activo')->orderBy('nombre')->get(),
            'proveedores' => $includeSensitiveValues
                ? DB::table('proveedores')->where('estatus', 'activo')->orderBy('nombre')->get()
                : collect(),
            'centrosCosto' => DB::table('centros_costo')->where('estatus', 'activo')->orderBy('clave')->get(),
            'tiposActivo' => DB::table('tipos_activo')->where('estatus', 'activo')->orderBy('descripcion')->get(),
            'monedas' => $this->financialCatalogs->currencies(),
            'estatusContables' => $this->financialCatalogs->accountingStatuses(),
        ];
    }

    private function findValorForEdit(int $id)
    {
        return $this->baseQuery(true)->where('v.id', $id)->first();
    }

    private function exportValues(
        $query,
        FilterValoresActivoRequest $request,
        string $format,
        bool $includeSensitiveValues
    ) {
        $columns = $this->exportColumns($includeSensitiveValues);
        $rows = (clone $query)
            ->orderByDesc('v.fecha_corte')
            ->orderByDesc('v.id')
            ->limit(self::EXPORT_LIMIT + 1)
            ->get();

        if ($rows->count() > self::EXPORT_LIMIT) {
            return redirect()
                ->route('valores', $request->except(['export']))
                ->withErrors([
                    'exportacion' => 'La exportación supera el límite de '
                        . number_format(self::EXPORT_LIMIT)
                        . ' registros. Aplica filtros más específicos.',
                ]);
        }

        $dataRows = $rows->map(function (object $row) use ($columns): array {
            $line = [];

            foreach (array_keys($columns) as $key) {
                $line[] = $this->safeSpreadsheetValue(data_get($row, $key));
            }

            return $line;
        })->all();

        $scope = $includeSensitiveValues ? 'completo' : 'operativo_basico';
        $filenameBase = 'valores_activo_swafi_' . $scope . '_' . now()->format('Ymd_His');

        try {
            if ($format === 'xlsx') {
                $contents = $this->xlsxExporter->exportBytes(
                    'Valores de activo fijo',
                    array_values($columns),
                    $dataRows
                );
            } elseif ($format === 'pdf') {
                $contents = $this->pdfExporter->export(
                    title: 'Consulta de valores de activo fijo SWAFI',
                    headers: array_values($columns),
                    rows: $dataRows,
                    metadata: [
                        'usuario' => session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                        'fecha' => now()->format('d/m/Y H:i:s'),
                        'filtros' => $this->valueFilterSummary($request, $includeSensitiveValues),
                    ]
                );
            }
        } catch (\Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_values_list_export',
                [
                    'format' => $format,
                    'scope' => $scope,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores', $request->except(['export']))
                ->withErrors([
                    'exportacion' => "No fue posible generar la exportación. Referencia: {$reference}.",
                ]);
        }

        $this->registerExportAudit(
            request: $request,
            format: $format,
            scope: $scope,
            rowCount: $rows->count(),
            columns: array_keys($columns)
        );

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($columns, $dataRows): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($columns), ',', '"', '');

                foreach ($dataRows as $row) {
                    fputcsv($output, $row, ',', '"', '');
                }

                fclose($output);
            }, $filenameBase . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($format === 'xlsx') {
            return response()->streamDownload(
                static function () use ($contents): void {
                    echo $contents;
                },
                $filenameBase . '.xlsx',
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.pdf"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function exportColumns(bool $includeSensitiveValues): array
    {
        $basic = [
            'numero_activo' => 'Número activo',
            'activo_descripcion' => 'Descripción',
            'planta_nombre' => 'Planta',
            'centro_costo_clave' => 'Centro de costo',
            'tipo_activo' => 'Tipo de activo',
            'estatus_operativo' => 'Estatus operativo',
            'estatus_documental' => 'Estatus documental',
            'estatus_contable' => 'Estatus contable',
            'conciliacion_cfdi' => 'Soporte XML',
            'fecha_corte' => 'Fecha de corte',
        ];

        if (!$includeSensitiveValues) {
            return $basic;
        }

        return [
            'numero_activo' => 'Número activo',
            'activo_descripcion' => 'Descripción',
            'folio_factura' => 'Folio factura',
            'proveedor_nombre' => 'Proveedor',
            'planta_nombre' => 'Planta',
            'centro_costo_clave' => 'Centro de costo',
            'tipo_activo' => 'Tipo de activo',
            'valor_fiscal' => 'Valor fiscal',
            'depreciacion_acumulada' => 'Depreciación acumulada Oracle ERP',
            'valor_en_libros' => 'Valor en libros Oracle ERP',
            'valor_financiero' => 'Valor financiero',
            'moneda' => 'Moneda',
            'tipo_cambio' => 'Tipo de cambio',
            'fecha_tipo_cambio' => 'Fecha tipo de cambio',
            'vida_util_meses' => 'Vida útil oficial meses',
            'fecha_corte' => 'Fecha de corte',
            'estatus_contable' => 'Estatus contable',
            'conciliacion_cfdi' => 'Estado técnico XML',
            'cfdi_total' => 'Total CFDI',
            'cfdi_moneda' => 'Moneda CFDI',
        ];
    }

    private function valueFilterSummary(
        FilterValoresActivoRequest $request,
        bool $includeSensitiveValues
    ): string {
        $allowed = [
            'numero_activo',
            'planta_id',
            'centro_costo_id',
            'tipo_activo_id',
            'estatus_contable',
            'conciliacion_cfdi',
        ];

        if ($includeSensitiveValues) {
            $allowed = array_merge($allowed, [
                'proveedor_id',
                'moneda',
                'fecha_desde',
                'fecha_hasta',
                'valor_desde',
                'valor_hasta',
            ]);
        }

        $parts = [];

        foreach ($allowed as $key) {
            $value = $request->validated($key);

            if ($value !== null && $value !== '') {
                $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
            }
        }

        return $parts === [] ? 'Sin filtros adicionales' : implode(' | ', $parts);
    }

    private function registerExportAudit(
        FilterValoresActivoRequest $request,
        string $format,
        string $scope,
        int $rowCount,
        array $columns
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => auth()->id(),
                'modulo' => 'M02 Control fiscal y financiero',
                'accion' => match ($format) {
                    'xlsx' => 'EXPORTACION_VALORES_XLSX',
                    'pdf' => 'EXPORTACION_VALORES_PDF',
                    default => 'EXPORTACION_VALORES_CSV',
                },
                'tabla_afectada' => 'valores_activo',
                'registro_clave' => $scope,
                'antes' => null,
                'despues' => json_encode([
                    'formato' => strtoupper($format),
                    'alcance' => $scope,
                    'total_exportado' => $rowCount,
                    'columnas' => $columns,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'asset_values_list_export_audit',
                [
                    'format' => $format,
                    'scope' => $scope,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );
        }
    }

    private function safeSpreadsheetValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param array<string, bool> $currencyRules
     * @param array<int, string> $statusKeys
     */
    private function validateImportPayload(
        array $payload,
        array $currencyRules,
        array $statusKeys
    ): ?string {
        return ValoresActivoImportService::validateImportPayload(
            $payload,
            $currencyRules,
            $statusKeys
        );
    }

    private function detectDelimiter(string $line): string
    {
        return ValoresActivoImportService::detectDelimiter($line);
    }

    /**
     * @param array<int, string|null> $headers
     * @return list<string>
     */
    private function normalizeImportHeaders(array $headers): array
    {
        return ValoresActivoImportService::normalizeImportHeaders($headers);
    }

    private function normalizeHeader(?string $value): string
    {
        return ValoresActivoImportService::normalizeHeader($value);
    }

    private function normalizeCell(?string $value): string
    {
        return ValoresActivoImportService::normalizeCell($value);
    }

    private function toDecimal(?string $value, int $scale = 2): ?float
    {
        return ValoresActivoImportService::toDecimal($value, $scale);
    }

    private function toInteger(?string $value): ?int
    {
        return ValoresActivoImportService::toInteger($value);
    }

    private function parseDate(?string $value): ?string
    {
        return ValoresActivoImportService::parseDate($value);
    }

    private function normalizeStatus(?string $value): ?string
    {
        return ValoresActivoImportService::normalizeStatus($value);
    }

    private function findOwnedImportBatch(string $uuid): ImportacionValores
    {
        return ImportacionValores::query()
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function canManageValues(): bool
    {
        return $this->authorization->canCurrentUser('valores.administrar');
    }

    private function canViewSensitiveValues(): bool
    {
        return $this->canManageValues()
            || $this->authorization->canCurrentUser('reportes.valores');
    }

    private function canExportValues(): bool
    {
        return $this->authorization->canCurrentUser('valores.ver');
    }

    private function abortUnlessCanManageValues(): void
    {
        abort_unless($this->canManageValues(), 403, 'No tienes permiso para modificar valores fiscales y financieros.');
    }

    private function registerAudit(string $asset, string $action, string $key, ?array $before, ?array $after): void
    {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $asset,
            'user_id' => auth()->id(),
            'modulo' => 'M02 Control fiscal y financiero',
            'accion' => mb_substr($action, 0, 40),
            'tabla_afectada' => 'valores_activo',
            'registro_clave' => $key,
            'antes' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
