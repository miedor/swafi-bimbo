<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistroMasivoRequest;
use App\Models\ImportacionMasiva;
use App\Services\RegistroMasivoService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistroMasivoController extends Controller
{
    public function __construct(
        private readonly RegistroMasivoService $importService,
        private readonly SimpleXlsxExporter $xlsxExporter
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
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
            $lote = $this->findOwnedBatch((string) $request->input('lote'));

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
            ->where('user_id', auth()->id())
            ->latest('id')
            ->limit(8)
            ->get();

        return view('swafi.registro-masivo', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'lote' => $lote,
            'filasPreview' => $filasPreview,
            'lotesRecientes' => $lotesRecientes,
            'previewStatus' => (string) $request->input('preview_status', ''),
        ]);
    }

    public function importar(ImportRegistroMasivoRequest $request): RedirectResponse
    {
        try {
            $batch = $this->importService->previsualizar(
                csvFile: $request->file('archivo_csv'),
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
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'archivo_csv' => 'No fue posible generar la previsualización. Verifica los archivos y vuelve a intentarlo.',
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
            report($exception);

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'lote' => 'La carga no fue aplicada. No se confirmó ningún cambio del lote.',
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

    public function exportarIncidencias(string $lote): RedirectResponse|StreamedResponse
    {
        $batch = $this->findOwnedBatch($lote);
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
            report($exception);

            return redirect()
                ->route('registro-masivo', ['lote' => $batch->uuid])
                ->withErrors([
                    'incidencias' => 'No fue posible generar el Excel de incidencias. Usa la descarga CSV disponible y comparte la referencia técnica si el problema continúa.',
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
            report($exception);
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
        $batch = $this->findOwnedBatch($lote);
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
            report($exception);
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

    public function plantillaCsv()
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
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
            ]);

            fputcsv($output, [
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
            ]);

            fclose($output);
        }, 'plantilla_registro_masivo_expedientes_swafi.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
        ];
    }

    private function exportCsv($query)
    {
        $rows = $query
            ->orderByDesc('e.created_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Numero activo',
                'Descripcion',
                'Folio factura',
                'UUID CFDI',
                'Proveedor',
                'RFC',
                'Planta',
                'Centro costo',
                'Fecha factura',
                'Monto factura',
                'Moneda',
                'Estatus',
                'Tiene PDF',
                'Tiene XML',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->numero_activo,
                    $row->activo_descripcion,
                    $row->folio_factura,
                    $row->uuid_cfdi,
                    $row->proveedor_nombre,
                    $row->proveedor_rfc,
                    $row->planta_nombre,
                    $row->centro_costo_clave,
                    $row->fecha_factura,
                    $row->monto_factura,
                    $row->moneda,
                    $row->estatus,
                    ((int) $row->total_pdf) > 0 ? 'Sí' : 'No',
                    ((int) $row->total_xml) > 0 ? 'Sí' : 'No',
                ]);
            }

            fclose($output);
        }, 'registro_masivo_expedientes_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

}
