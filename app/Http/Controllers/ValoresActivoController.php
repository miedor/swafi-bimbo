<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterValoresActivoRequest;
use App\Http\Requests\ImportValoresActivoRequest;
use App\Http\Requests\StoreValorActivoRequest;
use App\Models\ValorActivo;
use App\Services\CfdiValidationService;
use App\Services\DepreciacionReferencialService;
use App\Services\FinancialCatalogService;
use App\Services\SafeExceptionReporter;
use App\Services\SwafiAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class ValoresActivoController extends Controller
{
    public function __construct(
        private readonly SwafiAuthorizationService $authorization,
        private readonly SafeExceptionReporter $safeExceptions,
        private readonly FinancialCatalogService $financialCatalogs,
        private readonly DepreciacionReferencialService $depreciation
    ) {
    }

    public function index(FilterValoresActivoRequest $request)
    {
        $filters = $request->validated();
        $canViewSensitiveValues = $this->canViewSensitiveValues();
        $query = $this->baseQuery($canViewSensitiveValues);
        $this->applyFilters($query, $request, $canViewSensitiveValues);

        if ($request->input('export') === 'csv') {
            abort_unless(
                $this->canExportValues(),
                403,
                'No tienes permiso para exportar valores fiscales y financieros.'
            );

            return $this->exportCsv($query);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $resultados = $query
            ->orderByDesc('v.fecha_corte')
            ->orderByDesc('v.id')
            ->paginate($perPage)
            ->withQueryString();

        $valorEdit = null;

        if ($request->filled('editar_valor') && $this->canManageValues()) {
            $valorEdit = $this->findValorForEdit((int) $request->input('editar_valor'));
        }

        return view('swafi.valores', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos($canViewSensitiveValues),
            'filtros' => $filters,
            'valorEdit' => $valorEdit,
            'canAdministrarValores' => $this->canManageValues(),
            'canViewSensitiveValues' => $canViewSensitiveValues,
            'canExportarValores' => $this->canExportValues(),
            'canExportarExcel' => $canViewSensitiveValues
                && $this->authorization->canCurrentUser('reportes.exportar_excel'),
            'canExportarPdf' => $canViewSensitiveValues
                && $this->authorization->canCurrentUser('reportes.exportar_pdf'),
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

        $valueInBooks = $this->resolveValorEnLibros(
            (float) $data['valor_fiscal'],
            (float) $data['depreciacion_acumulada'],
            $data['valor_en_libros'] ?? null
        );

        $currency = strtoupper((string) $data['moneda']);
        $requiresExchangeRate = $this->financialCatalogs->currencyRequiresExchangeRate($currency);
        try {
            $depreciationReference = $this->resolveDepreciationReference($data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors([
                'depreciacion_referencial' => $exception->getMessage(),
            ]);
        }
        $payload = [
            'numero_activo' => $data['numero_activo'],
            'valor_fiscal' => $data['valor_fiscal'],
            'valor_financiero' => $data['valor_financiero'],
            'moneda' => $currency,
            'tipo_cambio' => $requiresExchangeRate ? ($data['tipo_cambio'] ?? null) : 1,
            'fecha_tipo_cambio' => $requiresExchangeRate ? ($data['fecha_tipo_cambio'] ?? null) : null,
            'origen_tipo_cambio' => $requiresExchangeRate ? ($data['origen_tipo_cambio'] ?? null) : null,
            'depreciacion_acumulada' => $data['depreciacion_acumulada'],
            'valor_en_libros' => $valueInBooks,
            'vida_util_meses' => $data['vida_util_meses'] ?? null,
            'metodo_depreciacion' => $depreciationReference['metodo_depreciacion'],
            'fecha_inicio_depreciacion' => $depreciationReference['fecha_inicio_depreciacion'],
            'valor_residual' => $depreciationReference['valor_residual'],
            'depreciacion_estimada' => $depreciationReference['depreciacion_estimada'],
            'valor_en_libros_estimado' => $depreciationReference['valor_en_libros_estimado'],
            'calculo_depreciacion_at' => $depreciationReference['calculo_depreciacion_at'],
            'estatus_contable' => $data['estatus_contable'],
            'motivo_cambio' => $data['motivo_cambio'] ?: ($existing ? null : 'Registro inicial de valores.'),
            'fecha_corte' => $data['fecha_corte'],
            'registrado_por' => auth()->id(),
        ];

        $reconciliation = $cfdiService->reconcileValuePayload($data['numero_activo'], $payload);

        if (!empty($reconciliation['blockingErrors'])) {
            return back()->withInput()->withErrors([
                'conciliacion_cfdi' => implode(' ', $reconciliation['blockingErrors']),
            ]);
        }

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
                ? 'Los valores se actualizaron y conciliaron contra el CFDI vigente.'
                : 'Los valores se registraron y conciliaron contra el CFDI vigente.');
    }

    public function importar(ImportValoresActivoRequest $request, CfdiValidationService $cfdiService)
    {
        $this->abortUnlessCanManageValues();
        $rows = file($request->file('archivo_csv')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!$rows || count($rows) < 2) {
            return back()->withErrors(['archivo_csv' => 'El archivo no contiene registros para importar.']);
        }

        $delimiter = $this->detectDelimiter($rows[0]);
        $headers = array_map(fn ($value) => $this->normalizeHeader($value), str_getcsv($rows[0], $delimiter));
        $indexes = array_flip($headers);
        $required = [
            'numero_activo',
            'valor_fiscal',
            'depreciacion_acumulada',
            'valor_financiero',
            'vida_util_meses',
            'fecha_corte',
            'estatus_contable',
        ];
        $missing = array_diff($required, $headers);

        if ($missing) {
            return back()->withErrors([
                'archivo_csv' => 'Faltan encabezados obligatorios: ' . implode(', ', $missing),
            ]);
        }

        $summary = ['procesados' => 0, 'insertados' => 0, 'actualizados' => 0, 'rechazados' => 0, 'errores' => []];
        $currencyRules = DB::table('monedas')
            ->where('estatus', 'activo')
            ->pluck('requiere_tipo_cambio', 'clave')
            ->mapWithKeys(fn (mixed $required, mixed $key): array => [
                mb_strtoupper((string) $key, 'UTF-8') => (bool) $required,
            ])
            ->all();
        $statusKeys = DB::table('estatus_contables')
            ->where('estatus', 'activo')
            ->pluck('clave')
            ->map(fn (mixed $key): string => mb_strtolower((string) $key, 'UTF-8'))
            ->all();
        $methodKeys = array_keys($this->financialCatalogs->depreciationMethods());

        DB::beginTransaction();

        try {
            foreach (array_slice($rows, 1) as $index => $line) {
                $lineNumber = $index + 2;
                $columns = str_getcsv($line, $delimiter);
                $get = fn (string $key) => $this->normalizeCell($columns[$indexes[$key] ?? -1] ?? '');
                $numeroActivo = strtoupper($get('numero_activo'));

                if ($numeroActivo === '') {
                    continue;
                }

                $summary['procesados']++;

                if (!DB::table('activos')->where('numero_activo', $numeroActivo)->exists()) {
                    $this->rejectRow($summary, $lineNumber, "el activo {$numeroActivo} no existe.");
                    continue;
                }

                $payload = [
                    'numero_activo' => $numeroActivo,
                    'valor_fiscal' => $this->toDecimal($get('valor_fiscal')),
                    'depreciacion_acumulada' => $this->toDecimal($get('depreciacion_acumulada')),
                    'valor_en_libros' => $this->toDecimal($get('valor_en_libros')),
                    'valor_financiero' => $this->toDecimal($get('valor_financiero')),
                    'vida_util_meses' => $this->toInteger($get('vida_util_meses')),
                    'metodo_depreciacion' => $this->normalizeHeader($get('metodo_depreciacion')) ?: null,
                    'fecha_inicio_depreciacion' => $this->parseDate($get('fecha_inicio_depreciacion')),
                    'valor_residual' => $this->toDecimal($get('valor_residual')),
                    'fecha_corte' => $this->parseDate($get('fecha_corte')),
                    'estatus_contable' => $this->normalizeStatus($get('estatus_contable')),
                    'moneda' => mb_strtoupper($get('moneda') ?: 'MXN', 'UTF-8'),
                    'tipo_cambio' => $this->toDecimal($get('tipo_cambio'), 6),
                    'fecha_tipo_cambio' => $this->parseDate($get('fecha_tipo_cambio')),
                    'origen_tipo_cambio' => $get('origen_tipo_cambio') ?: null,
                    'motivo_cambio' => $get('motivo_cambio') ?: 'Actualización mediante carga masiva.',
                    'registrado_por' => auth()->id(),
                ];

                if (array_key_exists($payload['moneda'], $currencyRules) && !$currencyRules[$payload['moneda']]) {
                    $payload['tipo_cambio'] = 1.0;
                    $payload['fecha_tipo_cambio'] = null;
                    $payload['origen_tipo_cambio'] = null;
                }

                if ($payload['valor_fiscal'] === null || $payload['valor_financiero'] === null || $payload['depreciacion_acumulada'] === null) {
                    $this->rejectRow($summary, $lineNumber, 'los importes obligatorios deben ser numéricos.');
                    continue;
                }

                if ($payload['valor_en_libros'] === null) {
                    $payload['valor_en_libros'] = $this->resolveValorEnLibros(
                        $payload['valor_fiscal'],
                        $payload['depreciacion_acumulada'],
                        null
                    );
                }

                $validationError = $this->validateImportPayload(
                    $payload,
                    $currencyRules,
                    $statusKeys,
                    $methodKeys
                );

                if ($validationError) {
                    $this->rejectRow($summary, $lineNumber, $validationError);
                    continue;
                }

                try {
                    $reference = $this->resolveDepreciationReference($payload);
                } catch (DomainException $exception) {
                    $this->rejectRow($summary, $lineNumber, $exception->getMessage());
                    continue;
                }

                $payload = array_merge($payload, $reference);
                $reconciliation = $cfdiService->reconcileValuePayload($numeroActivo, $payload);

                if ($reconciliation['blockingErrors']) {
                    $this->rejectRow($summary, $lineNumber, implode(' ', $reconciliation['blockingErrors']));
                    continue;
                }

                $payload['cfdi_validacion_id'] = $reconciliation['validation_id'];
                $payload['conciliacion_cfdi'] = $reconciliation['status'];
                $payload['conciliacion_detalle'] = $reconciliation['details'];

                $existing = ValorActivo::withTrashed()->where('numero_activo', $numeroActivo)->first();

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
                    $summary['actualizados']++;
                    $this->registerAudit(
                        $numeroActivo,
                        $wasDeleted ? 'IMPORTACION_VALOR_RESTAURACION' : 'IMPORTACION_VALOR_EDICION',
                        (string) $existing->id,
                        $before,
                        $existing->fresh()->toArray()
                    );
                } else {
                    $value = ValorActivo::create($payload);
                    $summary['insertados']++;
                    $this->registerAudit($numeroActivo, 'IMPORTACION_VALOR_ALTA', (string) $value->id, null, $value->toArray());
                }
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_values_bulk_import',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );

            return back()->withErrors([
                'archivo_csv' => "La importación fue revertida. Referencia: {$reference}.",
            ]);
        }

        return redirect()
            ->route('valores')
            ->with('success', 'La carga masiva de valores fue procesada.')
            ->with('import_summary', $summary);
    }

    public function plantillaCsv()
    {
        $this->abortUnlessCanManageValues();

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Numero activo', 'Valor fiscal', 'Depreciacion acumulada', 'Valor en libros',
                'Valor financiero', 'Moneda', 'Tipo cambio', 'Fecha tipo cambio',
                'Origen tipo cambio', 'Vida util meses', 'Metodo depreciacion',
                'Fecha inicio depreciacion', 'Valor residual', 'Fecha corte',
                'Estatus contable', 'Motivo cambio',
            ]);
            fputcsv($output, [
                'BIM-537028', '602700', '10045', '592655', '602700', 'MXN', '1', '', '',
                '60', 'linea_recta', '25/06/2026', '0', '25/06/2027', 'vigente',
                'Carga inicial validada contra CFDI.',
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
            'v.metodo_depreciacion',
            'v.fecha_inicio_depreciacion',
            'v.valor_residual',
            'v.depreciacion_estimada',
            'v.valor_en_libros_estimado',
            'v.calculo_depreciacion_at',
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
            'metodosDepreciacion' => $this->financialCatalogs->depreciationMethods(),
        ];
    }

    private function findValorForEdit(int $id)
    {
        return $this->baseQuery(true)->where('v.id', $id)->first();
    }

    private function exportCsv($query)
    {
        $rows = $query->orderByDesc('v.fecha_corte')->orderByDesc('v.id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Numero activo', 'Folio factura', 'Proveedor', 'Planta', 'Centro costo',
                'Tipo activo', 'Valor fiscal', 'Depreciacion acumulada', 'Valor en libros',
                'Valor financiero', 'Moneda', 'Tipo cambio', 'Fecha tipo cambio',
                'Vida util meses', 'Metodo depreciacion', 'Fecha inicio depreciacion',
                'Valor residual', 'Depreciacion estimada', 'Valor en libros estimado',
                'Fecha corte', 'Estatus contable', 'Conciliacion CFDI',
                'Total CFDI', 'Moneda CFDI',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->numero_activo, $row->folio_factura, $row->proveedor_nombre,
                    $row->planta_nombre, $row->centro_costo_clave, $row->tipo_activo,
                    $row->valor_fiscal, $row->depreciacion_acumulada, $row->valor_en_libros,
                    $row->valor_financiero, $row->moneda, $row->tipo_cambio,
                    $row->fecha_tipo_cambio, $row->vida_util_meses, $row->metodo_depreciacion,
                    $row->fecha_inicio_depreciacion, $row->valor_residual,
                    $row->depreciacion_estimada, $row->valor_en_libros_estimado, $row->fecha_corte,
                    $row->estatus_contable, $row->conciliacion_cfdi, $row->cfdi_total,
                    $row->cfdi_moneda,
                ]);
            }
            fclose($output);
        }, 'valores_activo_swafi_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param array<string, bool> $currencyRules
     * @param array<int, string> $statusKeys
     * @param array<int, string> $methodKeys
     */
    private function validateImportPayload(
        array $payload,
        array $currencyRules,
        array $statusKeys,
        array $methodKeys
    ): ?string {
        if (!in_array((string) $payload['estatus_contable'], $statusKeys, true)) {
            return 'el estatus contable no existe o se encuentra inactivo.';
        }

        if (!$payload['fecha_corte']) {
            return 'la fecha de corte no es válida.';
        }

        if (!$payload['vida_util_meses'] || $payload['vida_util_meses'] <= 0 || $payload['vida_util_meses'] > 1200) {
            return 'la vida útil debe ser un entero entre 1 y 1200 meses.';
        }

        foreach ([
            'valor_fiscal' => 'valor fiscal',
            'valor_financiero' => 'valor financiero',
            'depreciacion_acumulada' => 'depreciación acumulada',
            'valor_en_libros' => 'valor en libros',
        ] as $key => $label) {
            if ($payload[$key] === null || (float) $payload[$key] < 0) {
                return "el {$label} debe ser numérico y no puede ser negativo.";
            }
        }

        if (
            $payload['estatus_contable'] !== 'baja'
            && ((float) $payload['valor_fiscal'] <= 0 || (float) $payload['valor_financiero'] <= 0)
        ) {
            return 'un activo vigente o en revisión requiere valor fiscal y valor financiero mayores a cero.';
        }

        if (
            $payload['estatus_contable'] !== 'baja'
            && (float) $payload['depreciacion_acumulada'] > (float) $payload['valor_fiscal']
        ) {
            return 'la depreciación acumulada no puede superar el valor fiscal.';
        }

        if (
            $payload['estatus_contable'] !== 'baja'
            && (float) $payload['valor_en_libros'] > (float) $payload['valor_fiscal']
        ) {
            return 'el valor en libros no puede superar el valor fiscal.';
        }

        $currency = (string) $payload['moneda'];

        if (!array_key_exists($currency, $currencyRules)) {
            return 'la moneda no existe o se encuentra inactiva.';
        }

        if (
            $currencyRules[$currency]
            && (
                !$payload['tipo_cambio']
                || !$payload['fecha_tipo_cambio']
                || !$payload['origen_tipo_cambio']
            )
        ) {
            return 'la moneda seleccionada requiere tipo de cambio, fecha y origen.';
        }

        $method = trim((string) ($payload['metodo_depreciacion'] ?? ''));
        $startDate = $payload['fecha_inicio_depreciacion'] ?? null;
        $residualValue = $payload['valor_residual'] ?? null;
        $hasReferenceData = $method !== '' || $startDate !== null || $residualValue !== null;

        if (!$hasReferenceData) {
            return null;
        }

        if ($method === '' || !in_array($method, $methodKeys, true)) {
            return 'el método de depreciación no está implementado o no es válido.';
        }

        if (!$startDate) {
            return 'la fecha de inicio de depreciación no es válida.';
        }

        if ($residualValue === null || (float) $residualValue < 0) {
            return 'el valor residual debe ser numérico y no puede ser negativo.';
        }

        if ((float) $residualValue > (float) $payload['valor_financiero']) {
            return 'el valor residual no puede superar el valor financiero.';
        }

        if ((string) $startDate > (string) $payload['fecha_corte']) {
            return 'la fecha de inicio de depreciación no puede ser posterior a la fecha de corte.';
        }

        return null;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function normalizeHeader(?string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function normalizeCell(?string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }

    private function toDecimal(?string $value, int $scale = 2): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(['$', ' ', "\u{00A0}"], '', $value);

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $commaCount = substr_count($value, ',');
            $decimals = strlen($value) - $lastComma - 1;

            if ($commaCount === 1 && $decimals > 0 && $decimals <= $scale && $decimals !== 3) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $decimalPart = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimalPart;
        }

        return is_numeric($value)
            ? round((float) $value, $scale)
            : null;
    }

    private function toInteger(?string $value): ?int
    {
        return filter_var(trim((string) $value), FILTER_VALIDATE_INT) !== false ? (int) $value : null;
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeStatus(?string $value): ?string
    {
        $value = $this->normalizeHeader($value);

        return match ($value) {
            'vigente', 'activo' => 'vigente',
            'en_revision', 'revision', 'en_revicion' => 'en_revision',
            'baja', 'dado_de_baja' => 'baja',
            default => $value !== '' ? $value : null,
        };
    }

    private function resolveValorEnLibros(float $fiscal, float $depreciation, mixed $book): float
    {
        return $book === null || $book === '' ? max(round($fiscal - $depreciation, 2), 0) : round((float) $book, 2);
    }

    /**
     * @return array{
     *     metodo_depreciacion:?string,
     *     fecha_inicio_depreciacion:?string,
     *     valor_residual:float,
     *     depreciacion_estimada:?float,
     *     valor_en_libros_estimado:?float,
     *     calculo_depreciacion_at:mixed
     * }
     */
    private function resolveDepreciationReference(array $data): array
    {
        $method = trim((string) ($data['metodo_depreciacion'] ?? ''));
        $startDate = $data['fecha_inicio_depreciacion'] ?? null;
        $residualValue = $data['valor_residual'] ?? null;
        $hasReferenceData = $method !== '' || $startDate !== null || $residualValue !== null;

        if (!$hasReferenceData) {
            return [
                'metodo_depreciacion' => null,
                'fecha_inicio_depreciacion' => null,
                'valor_residual' => 0.0,
                'depreciacion_estimada' => null,
                'valor_en_libros_estimado' => null,
                'calculo_depreciacion_at' => null,
            ];
        }

        $result = $this->depreciation->calculate(
            method: $method,
            financialValue: (float) ($data['valor_financiero'] ?? 0),
            residualValue: (float) ($residualValue ?? 0),
            usefulLifeMonths: (int) ($data['vida_util_meses'] ?? 0),
            startDate: (string) $startDate,
            cutoffDate: (string) ($data['fecha_corte'] ?? '')
        );

        return [
            'metodo_depreciacion' => $result['metodo'],
            'fecha_inicio_depreciacion' => $result['fecha_inicio'],
            'valor_residual' => $result['valor_residual'],
            'depreciacion_estimada' => $result['depreciacion_estimada'],
            'valor_en_libros_estimado' => $result['valor_en_libros_estimado'],
            'calculo_depreciacion_at' => now(),
        ];
    }

    private function rejectRow(array &$summary, int $line, string $message): void
    {
        $summary['rechazados']++;
        $summary['errores'][] = "Fila {$line}: {$message}";
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
        return $this->canViewSensitiveValues()
            && $this->authorization->canCurrentUser('reportes.exportar');
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
