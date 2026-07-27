<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterValorActivoHistoryRequest;
use App\Services\SafeExceptionReporter;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\ValorActivoHistoryService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ValorActivoHistoryController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly ValorActivoHistoryService $historyService,
        private readonly SafeExceptionReporter $safeExceptions,
        private readonly SimpleXlsxExporter $xlsxExporter,
        private readonly SimplePdfTableExporter $pdfExporter
    ) {
    }

    public function index(
        FilterValorActivoHistoryRequest $request,
        string $numeroActivo
    ) {
        $numeroActivo = mb_strtoupper(trim($numeroActivo), 'UTF-8');
        $activo = $this->findAsset($numeroActivo);

        abort_if(!$activo, 404, 'El activo solicitado no existe en SWAFI.');

        $filters = $request->validated();
        $exportFormat = strtolower((string) ($filters['export'] ?? ''));

        if (in_array($exportFormat, ['csv', 'xlsx', 'pdf'], true)) {
            return $this->exportHistory(
                request: $request,
                numeroActivo: $numeroActivo,
                filters: $filters,
                format: $exportFormat
            );
        }

        $history = $this->historyService->paginate($numeroActivo, $filters);

        $this->registerQueryAudit($numeroActivo, $filters);

        return view('swafi.valores-historial', [
            'activo' => $activo,
            'valorActual' => $this->findCurrentValue($numeroActivo),
            'historial' => $history,
            'resumen' => $this->historyService->summary($numeroActivo),
            'accionesDisponibles' => $this->historyService->availableActions($numeroActivo),
            'usuariosDisponibles' => $this->historyService->availableUsers($numeroActivo),
            'filtros' => $filters,
        ]);
    }

    private function exportHistory(
        FilterValorActivoHistoryRequest $request,
        string $numeroActivo,
        array $filters,
        string $format
    ) {
        $rows = $this->historyService->exportRows(
            $numeroActivo,
            $filters,
            self::EXPORT_LIMIT
        );

        if ($rows->count() > self::EXPORT_LIMIT) {
            return redirect()
                ->route('valores.historial', array_merge(
                    ['numeroActivo' => $numeroActivo],
                    $request->except(['export'])
                ))
                ->withErrors([
                    'exportacion' => 'La exportación supera el límite de '
                        . number_format(self::EXPORT_LIMIT)
                        . ' eventos. Aplica filtros más específicos.',
                ]);
        }

        $columns = [
            'fecha_evento' => 'Fecha',
            'accion_label' => 'Acción',
            'usuario_visible' => 'Usuario',
            'cambios' => 'Cambios identificados',
            'registro_clave' => 'Registro',
            'ip' => 'IP',
        ];

        $dataRows = $rows->map(function (object $entry): array {
            $changes = collect($entry->changes ?? [])
                ->map(function (array $change): string {
                    return ($change['label'] ?? $change['field'] ?? 'Campo')
                        . ': '
                        . ($change['before'] ?? 'Sin valor')
                        . ' → '
                        . ($change['after'] ?? 'Sin valor');
                })
                ->implode(' | ');

            return [
                $entry->fecha_evento,
                $this->safeSpreadsheetValue($entry->accion_label),
                $this->safeSpreadsheetValue($entry->usuario_visible),
                $this->safeSpreadsheetValue($changes !== '' ? $changes : 'Sin cambios de negocio identificados'),
                $this->safeSpreadsheetValue($entry->registro_clave),
                $this->safeSpreadsheetValue($entry->ip),
            ];
        })->all();

        $filenameBase = 'historial_valores_' . $numeroActivo . '_' . now()->format('Ymd_His');

        try {
            if ($format === 'xlsx') {
                $contents = $this->xlsxExporter->exportBytes(
                    'Histórico de valores ' . $numeroActivo,
                    array_values($columns),
                    $dataRows
                );
            } elseif ($format === 'pdf') {
                $contents = $this->pdfExporter->export(
                    title: 'Histórico fiscal y financiero · ' . $numeroActivo,
                    headers: array_values($columns),
                    rows: $dataRows,
                    metadata: [
                        'usuario' => session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                        'fecha' => now()->format('d/m/Y H:i:s'),
                        'filtros' => $this->historyFilterSummary($filters),
                    ]
                );
            }
        } catch (Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_value_history_export',
                [
                    'asset_number' => $numeroActivo,
                    'format' => $format,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores.historial', array_merge(
                    ['numeroActivo' => $numeroActivo],
                    $request->except(['export'])
                ))
                ->withErrors([
                    'exportacion' => "No fue posible generar la exportación. Referencia: {$reference}.",
                ]);
        }

        $this->registerExportAudit(
            request: $request,
            numeroActivo: $numeroActivo,
            format: $format,
            rowCount: $rows->count()
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

    private function registerExportAudit(
        FilterValorActivoHistoryRequest $request,
        string $numeroActivo,
        string $format,
        int $rowCount
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => auth()->id(),
                'modulo' => 'M02 Control fiscal y financiero',
                'accion' => match ($format) {
                    'xlsx' => 'EXPORTACION_HIST_VAL_XLSX',
                    'pdf' => 'EXPORTACION_HIST_VAL_PDF',
                    default => 'EXPORTACION_HIST_VAL_CSV',
                },
                'tabla_afectada' => 'bitacora_auditoria',
                'registro_clave' => $numeroActivo,
                'antes' => null,
                'despues' => json_encode([
                    'formato' => strtoupper($format),
                    'total_exportado' => $rowCount,
                    'filtros' => array_intersect_key($request->validated(), array_flip([
                        'accion',
                        'usuario_id',
                        'fecha_desde',
                        'fecha_hasta',
                    ])),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'asset_value_history_export_audit',
                [
                    'asset_number' => $numeroActivo,
                    'format' => $format,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );
        }
    }

    private function historyFilterSummary(array $filters): string
    {
        $parts = [];

        foreach (['accion', 'usuario_id', 'fecha_desde', 'fecha_hasta'] as $key) {
            $value = $filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
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

    private function findAsset(string $numeroActivo): ?object
    {
        return DB::table('activos as a')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->where('a.numero_activo', $numeroActivo)
            ->select([
                'a.numero_activo',
                'a.descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'a.activo',
                'p.nombre as proveedor_nombre',
                'pl.nombre as planta_nombre',
                'cc.clave as centro_costo_clave',
                'ta.descripcion as tipo_activo',
            ])
            ->first();
    }

    private function findCurrentValue(string $numeroActivo): ?object
    {
        return DB::table('valores_activo as v')
            ->where('v.numero_activo', $numeroActivo)
            ->select([
                'v.id',
                'v.valor_fiscal',
                'v.valor_financiero',
                'v.moneda',
                'v.tipo_cambio',
                'v.depreciacion_acumulada',
                'v.valor_en_libros',
                'v.vida_util_meses',
                'v.estatus_contable',
                'v.conciliacion_cfdi',
                'v.fecha_corte',
                'v.motivo_cambio',
                'v.deleted_at',
                'v.updated_at',
            ])
            ->first();
    }

    private function registerQueryAudit(string $numeroActivo, array $filters): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => auth()->id(),
                'modulo' => 'M02 Control fiscal y financiero',
                'accion' => 'CONSULTA_HIST_VALORES',
                'tabla_afectada' => 'bitacora_auditoria',
                'registro_clave' => $numeroActivo,
                'antes' => null,
                'despues' => json_encode([
                    'filtros' => array_intersect_key($filters, array_flip([
                        'accion',
                        'usuario_id',
                        'fecha_desde',
                        'fecha_hasta',
                        'per_page',
                    ])),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'asset_value_history_query_audit',
                [
                    'asset_number' => $numeroActivo,
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );
        }
    }
}
