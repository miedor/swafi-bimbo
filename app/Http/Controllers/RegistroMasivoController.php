<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistroMasivoRequest;
use App\Http\Requests\RevertImportacionMasivaRequest;
use App\Models\ImportacionMasiva;
use App\Services\AssetStatusCatalogService;
use App\Services\BulkImportRollbackService;
use App\Services\RegistroMasivoService;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\SwafiAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistroMasivoController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly RegistroMasivoService $importService,
        private readonly BulkImportRollbackService $rollbackService,
        private readonly SimpleXlsxExporter $xlsxExporter,
        private readonly SimplePdfTableExporter $pdfExporter,
        private readonly SwafiAuthorizationService $authorization,
        private readonly AssetStatusCatalogService $assetStatuses
    ) {
    }

    public function index(Request $request)
    {
        $request->validate([
            'estatus' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('estatus_documentales', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'export' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
        ], [
            'estatus.exists' => 'El estatus documental seleccionado no existe o está inactivo.',
            'export.in' => 'El formato de exportación solicitado no está permitido.',
        ]);

        $canRollbackImports = $this->authorization
            ->canCurrentUser('expedientes.revertir_importacion');
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $exportFormat = strtolower((string) $request->input('export'));

        if (in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            return $this->exportResults($query, $request, $exportFormat);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true)
            ? $perPage
            : 10;

        $resultados = $query
            ->orderByDesc('e.created_at')
            ->paginate($perPage)
            ->withQueryString();

        $lote = null;
        $filasPreview = null;

        if ($request->filled('lote')) {
            $lote = $this->findViewableBatch((string) $request->input('lote'), $canRollbackImports);

            $previewQuery = $lote->filas()->orderBy('numero_fila');
            $previewStatus = (string) $request->input('preview_status', '');

            if (in_array($previewStatus, ['aceptada', 'observada', 'rechazada'], true)) {
                $previewQuery->where('estatus', $previewStatus);
            }

            $filasPreview = $previewQuery
                ->paginate(25, ['*'], 'preview_page')
                ->withQueryString();
        }

        $lotesRecientes = ImportacionMasiva::query()
            ->with(['usuario:id,name,email'])
            ->when(
                !$canRollbackImports,
                fn ($query) => $query->where('user_id', auth()->id())
            )
            ->latest('id')
            ->limit($canRollbackImports ? 12 : 8)
            ->get();

        return view('swafi.registro-masivo', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'lote' => $lote,
            'filasPreview' => $filasPreview,
            'lotesRecientes' => $lotesRecientes,
            'previewStatus' => (string) $request->input('preview_status', ''),
            'canRollbackImports' => $canRollbackImports,
        ]);
    }

    public function importar(ImportRegistroMasivoRequest $request): RedirectResponse
    {
        try {
            $batch = $this->importService->previsualizar(
                layoutFile: $request->file('archivo_csv'),
                zipFile: $request->file('archivo_zip'),
                userId: auth()->id()
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->with(
                    'success',
                    'La previsualización fue generada. Revisa las filas antes de confirmar la carga.'
                );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'bulk_import_preview',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return back()
                ->withInput()
                ->withErrors([
                    'archivo_csv' => "No fue posible generar la previsualización. Referencia: {$reference}.",
                ]);
        }
    }

    public function aplicar(Request $request, string $lote): RedirectResponse
    {
        $request->validate([
            'confirmar_aplicacion' => ['accepted'],
        ], [
            'confirmar_aplicacion.accepted' => 'Debes confirmar que revisaste la previsualización antes de aplicar el lote.',
        ]);

        $batch = $this->findOwnedBatch($lote);

        try {
            $summary = $this->importService->aplicar(
                $batch,
                auth()->id()
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->with('success', 'La carga masiva fue aplicada correctamente.')
                ->with('import_summary', $summary);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'bulk_import_apply',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'lote' => "La carga no fue aplicada. No se confirmó ningún cambio. Referencia: {$reference}.",
                ]);
        }
    }

    public function cancelar(string $lote): RedirectResponse
    {
        $batch = $this->findOwnedBatch($lote);

        $this->importService->cancelar($batch, auth()->id());

        return redirect()
            ->route('registro-masivo')
            ->with('success', 'La previsualización fue cancelada sin modificar activos ni expedientes.');
    }

    public function revertir(
        RevertImportacionMasivaRequest $request,
        string $lote
    ): RedirectResponse {
        $batch = $this->findViewableBatch($lote, true);

        try {
            $summary = $this->rollbackService->revertir(
                batch: $batch,
                userId: (int) auth()->id(),
                reason: (string) $request->validated('motivo_reversion')
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->with(
                    'success',
                    'La importación fue revertida de forma controlada. Los documentos permanecen resguardados para auditoría.'
                )
                ->with('rollback_summary', $summary);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'bulk_import_rollback',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withInput()
                ->withErrors([
                    'lote' => "No fue posible revertir el lote. No se confirmó ningún cambio. Referencia: {$reference}.",
                ]);
        }
    }

    public function exportarIncidencias(string $lote): RedirectResponse|StreamedResponse
    {
        $batch = $this->findViewableBatch(
            $lote,
            $this->authorization->canCurrentUser('expedientes.revertir_importacion')
        );
        $rows = $this->incidentRows($batch);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'incidencias' => 'El lote no contiene filas observadas o rechazadas para exportar.',
                ]);
        }

        $dataRows = $this->incidentDataRows($rows);

        try {
            $contents = $this->xlsxExporter->exportBytes(
                'Incidencias importación',
                $this->incidentHeaders(),
                $dataRows
            );
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'bulk_import_incidents_excel',
                [
                    'batch_id' => $batch->id,
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'incidencias' => "No fue posible generar el Excel de incidencias. Referencia: {$reference}.",
                ]);
        }

        try {
            $this->importService->registrarExportacionIncidencias(
                batch: $batch,
                userId: auth()->id(),
                format: 'xlsx',
                rowCount: $rows->count()
            );
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'http_controllers_registromasivocontroller_exception_5'
            );
        }

        $filename = 'incidencias_importacion_' . $batch->uuid . '.xlsx';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function exportarIncidenciasCsv(string $lote): RedirectResponse|StreamedResponse
    {
        $batch = $this->findViewableBatch(
            $lote,
            $this->authorization->canCurrentUser('expedientes.revertir_importacion')
        );
        $rows = $this->incidentRows($batch);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'incidencias' => 'El lote no contiene filas observadas o rechazadas para exportar.',
                ]);
        }

        $headers = $this->incidentHeaders();
        $dataRows = $this->incidentDataRows($rows);

        try {
            $this->importService->registrarExportacionIncidencias(
                batch: $batch,
                userId: auth()->id(),
                format: 'csv',
                rowCount: $rows->count()
            );
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'http_controllers_registromasivocontroller_exception_6'
            );
        }

        return response()->streamDownload(
            static function () use ($headers, $dataRows): void {
                $output = fopen('php://output', 'wb');

                if (!is_resource($output)) {
                    throw new \RuntimeException('No fue posible iniciar la descarga CSV.');
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers);

                foreach ($dataRows as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
            },
            'incidencias_importacion_' . $batch->uuid . '.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function incidentRows(ImportacionMasiva $batch): Collection
    {
        return $batch->filas()
            ->whereIn('estatus', ['observada', 'rechazada'])
            ->orderBy('numero_fila')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function incidentHeaders(): array
    {
        return [
            'Fila',
            'Estatus',
            'Acción propuesta',
            'Número de activo',
            'Folio factura',
            'UUID CFDI',
            'Errores',
            'Advertencias',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function incidentDataRows(Collection $rows): array
    {
        return $rows->map(function ($row): array {
            $data = is_array($row->datos) ? $row->datos : [];

            return [
                (int) $row->numero_fila,
                ucfirst((string) $row->estatus),
                $row->accion ? ucfirst((string) $row->accion) : 'No aplicable',
                (string) ($data['numero_activo'] ?? ''),
                (string) ($data['folio_factura'] ?? ''),
                (string) ($data['uuid_cfdi'] ?? ''),
                implode(' | ', $this->normalizeIncidentMessages($row->errores)),
                implode(' | ', $this->normalizeIncidentMessages($row->advertencias)),
            ];
        })->all();
    }

    /**
     * @return array<int, string>
     */
    private function normalizeIncidentMessages(mixed $messages): array
    {
        if (is_array($messages)) {
            return array_values(array_filter(
                array_map(static fn (mixed $message): string => trim((string) $message), $messages),
                static fn (string $message): bool => $message !== ''
            ));
        }

        if (is_string($messages) && trim($messages) !== '') {
            $decoded = json_decode($messages, true);

            if (is_array($decoded)) {
                return $this->normalizeIncidentMessages($decoded);
            }

            return [trim($messages)];
        }

        return [];
    }

    private function findOwnedBatch(string $uuid): ImportacionMasiva
    {
        return ImportacionMasiva::query()
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function findViewableBatch(
        string $uuid,
        bool $canViewAll
    ): ImportacionMasiva {
        return ImportacionMasiva::query()
            ->with(['usuario:id,name,email'])
            ->where('uuid', $uuid)
            ->when(
                !$canViewAll,
                fn ($query) => $query->where('user_id', auth()->id())
            )
            ->firstOrFail();
    }

    public function plantillaCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            if (!is_resource($output)) {
                throw new \RuntimeException('No fue posible generar la plantilla CSV.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $this->templateHeaders(), ',', '"', '');
            fputcsv($output, $this->templateExampleRow(), ',', '"', '');
            fclose($output);
        }, 'plantilla_registro_masivo_expedientes_swafi.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function plantillaXlsx(): RedirectResponse|StreamedResponse
    {
        try {
            $bytes = $this->xlsxExporter->exportBytes(
                'Registro masivo',
                $this->templateHeaders(),
                [$this->templateExampleRow()]
            );
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'bulk_import_xlsx_template',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return redirect()
                ->route('registro-masivo')
                ->withErrors([
                    'archivo_csv' => "No fue posible generar la plantilla Excel. Referencia: {$reference}.",
                ]);
        }

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            'plantilla_registro_masivo_expedientes_swafi.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function templateHeaders(): array
    {
        return [
            'Numero activo',
            'Descripcion',
            'Folio factura',
            'UUID CFDI',
            'Fecha factura',
            'Monto factura',
            'Moneda',
            'Proveedor RFC',
            'Tipo activo clave',
            'Centro costo clave',
            'Planta clave',
            'Ubicacion codigo',
            'Responsable correo',
            'Serie',
            'Marca',
            'Modelo',
            'Fecha adquisicion',
            'Estatus operativo',
            'Documento PDF',
            'Documento XML',
            'Observaciones',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function templateExampleRow(): array
    {
        return [
            'BIM-537028',
            'ARTESA N° 1',
            'FAC-000184',
            'A1B2C3D4-E5F6-7890-ABCD-000000000184',
            '25/06/2026',
            '602700',
            'MXN',
            'ACM010101ABC',
            'EQP',
            'CC-PLA-200',
            'PLT-SM',
            'UBI-SM-PRO-L3-PB',
            'jorge.mendez@bimbo.local',
            'SER-537028',
            'Bimbo Industrial',
            'ART-2026',
            '25/06/2026',
            'en_operacion',
            'factura_184.pdf|evidencia_recepcion_184.pdf',
            'factura_184.xml|complemento_184.xml',
            'Carga masiva de expediente con varios documentos PDF/XML separados por pipe.',
        ];
    }

    private function baseQuery()
    {
        $documentCounts = DB::table('documentos_expediente')
            ->select(
                'expediente_id',
                DB::raw("SUM(CASE WHEN tipo_documento = 'PDF' AND vigente = 1 THEN 1 ELSE 0 END) as total_pdf"),
                DB::raw("SUM(CASE WHEN tipo_documento = 'XML' AND vigente = 1 THEN 1 ELSE 0 END) as total_xml")
            )
            ->groupBy('expediente_id');

        return DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoinSub($documentCounts, 'dc', function ($join) {
                $join->on('dc.expediente_id', '=', 'e.id');
            })
            ->whereNull('e.deleted_at')
            ->select([
                'e.id as expediente_id',
                'e.numero_activo',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                'e.estatus',
                'e.created_at',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'p.id as proveedor_id',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'pl.id as planta_id',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                DB::raw('COALESCE(dc.total_pdf, 0) as total_pdf'),
                DB::raw('COALESCE(dc.total_xml, 0) as total_xml'),
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('numero_activo')) {
            $query->where('e.numero_activo', 'like', '%' . $request->numero_activo . '%');
        }

        if ($request->filled('folio_factura')) {
            $query->where('e.folio_factura', 'like', '%' . $request->folio_factura . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', $request->planta_id);
        }

        if ($request->filled('proveedor_id')) {
            $query->where('a.proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('estatus')) {
            $query->where('e.estatus', $request->estatus);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('e.fecha_factura', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('e.fecha_factura', '<=', $request->fecha_hasta);
        }

        if ($request->filled('monto_desde')) {
            $query->where('e.monto_factura', '>=', $request->monto_desde);
        }

        if ($request->filled('monto_hasta')) {
            $query->where('e.monto_factura', '<=', $request->monto_hasta);
        }
    }

    private function catalogos(): array
    {
        return [
            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'proveedores' => DB::table('proveedores')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'estatusDocumentales' => $this->assetStatuses->documentaryOptions(),
        ];
    }

    private function exportResults($query, Request $request, string $format)
    {
        $columns = [
            'numero_activo' => 'Número activo',
            'activo_descripcion' => 'Descripción',
            'folio_factura' => 'Folio factura',
            'uuid_cfdi' => 'UUID CFDI',
            'proveedor_nombre' => 'Proveedor',
            'proveedor_rfc' => 'RFC',
            'planta_nombre' => 'Planta',
            'centro_costo_clave' => 'Centro de costo',
            'fecha_factura' => 'Fecha factura',
            'monto_factura' => 'Monto factura',
            'moneda' => 'Moneda',
            'estatus' => 'Estatus documental',
            'tiene_pdf' => 'Tiene PDF',
            'tiene_xml' => 'Tiene XML',
        ];

        $rows = (clone $query)
            ->orderByDesc('e.created_at')
            ->limit(self::EXPORT_LIMIT + 1)
            ->get();

        if ($rows->count() > self::EXPORT_LIMIT) {
            return redirect()
                ->route('registro-masivo', $request->except(['export']))
                ->withErrors([
                    'exportacion' => 'La exportación supera el límite de '
                        . number_format(self::EXPORT_LIMIT)
                        . ' registros. Aplica filtros más específicos.',
                ]);
        }

        $dataRows = $rows->map(function (object $row): array {
            return [
                $this->safeSpreadsheetValue($row->numero_activo),
                $this->safeSpreadsheetValue($row->activo_descripcion),
                $this->safeSpreadsheetValue($row->folio_factura),
                $this->safeSpreadsheetValue($row->uuid_cfdi),
                $this->safeSpreadsheetValue($row->proveedor_nombre),
                $this->safeSpreadsheetValue($row->proveedor_rfc),
                $this->safeSpreadsheetValue($row->planta_nombre),
                $this->safeSpreadsheetValue($row->centro_costo_clave),
                $row->fecha_factura,
                $row->monto_factura,
                $this->safeSpreadsheetValue($row->moneda),
                $this->safeSpreadsheetValue($row->estatus),
                ((int) $row->total_pdf) > 0 ? 'Sí' : 'No',
                ((int) $row->total_xml) > 0 ? 'Sí' : 'No',
            ];
        })->all();

        $filenameBase = 'registro_masivo_expedientes_swafi_' . now()->format('Ymd_His');

        try {
            if ($format === 'xlsx') {
                $contents = $this->xlsxExporter->exportBytes(
                    'Expedientes registrados por carga masiva',
                    array_values($columns),
                    $dataRows
                );
            } elseif ($format === 'pdf') {
                $contents = $this->pdfExporter->export(
                    title: 'Consulta de expedientes por registro masivo SWAFI',
                    headers: array_values($columns),
                    rows: $dataRows,
                    metadata: [
                        'usuario' => session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                        'fecha' => now()->format('d/m/Y H:i:s'),
                        'filtros' => $this->exportFilterSummary($request),
                    ]
                );
            }
        } catch (\Throwable $exception) {
            $reference = app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'mass_registration_list_export',
                [
                    'format' => $format,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );

            return redirect()
                ->route('registro-masivo', $request->except(['export']))
                ->withErrors([
                    'exportacion' => "No fue posible generar la exportación. Referencia: {$reference}.",
                ]);
        }

        $this->registerListExportAudit($request, $format, $rows->count());

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

    private function registerListExportAudit(Request $request, string $format, int $rowCount): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => auth()->id(),
                'modulo' => 'M01 Gestión de expedientes',
                'accion' => match ($format) {
                    'xlsx' => 'EXPORTACION_REG_MASIVO_XLSX',
                    'pdf' => 'EXPORTACION_REG_MASIVO_PDF',
                    default => 'EXPORTACION_REG_MASIVO_CSV',
                },
                'tabla_afectada' => 'expedientes',
                'registro_clave' => 'consulta_registro_masivo',
                'antes' => null,
                'despues' => json_encode([
                    'formato' => strtoupper($format),
                    'total_exportado' => $rowCount,
                    'filtros' => array_intersect_key($request->all(), array_flip([
                        'numero_activo',
                        'folio_factura',
                        'planta_id',
                        'proveedor_id',
                        'estatus',
                        'fecha_desde',
                        'fecha_hasta',
                        'monto_desde',
                        'monto_hasta',
                    ])),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'mass_registration_list_export_audit',
                [
                    'format' => $format,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );
        }
    }

    private function exportFilterSummary(Request $request): string
    {
        $parts = [];

        foreach ([
            'numero_activo',
            'folio_factura',
            'planta_id',
            'proveedor_id',
            'estatus',
            'fecha_desde',
            'fecha_hasta',
            'monto_desde',
            'monto_hasta',
        ] as $key) {
            if ($request->filled($key)) {
                $parts[] = str_replace('_', ' ', $key) . ': ' . $request->input($key);
            }
        }

        return $parts === [] ? 'Sin filtros adicionales' : implode(' | ', $parts);
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

}
