<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistroMasivoRequest;
use App\Models\Activo;
use App\Models\DocumentoExpediente;
use App\Models\Expediente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistroMasivoController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $resultados = $query
            ->orderByDesc('e.created_at')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('swafi.registro-masivo', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
        ]);
    }

    public function importar(ImportRegistroMasivoRequest $request)
    {
        $file = $request->file('archivo_csv');
        $rows = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!$rows || count($rows) < 2) {
            return back()->withErrors([
                'archivo_csv' => 'El archivo no contiene registros para importar.',
            ]);
        }

        $delimiter = $this->detectDelimiter($rows[0]);
        $headers = str_getcsv($rows[0], $delimiter);

        $normalizedHeaders = array_map(
            fn ($header) => $this->normalizeHeader($header),
            $headers
        );

        $requiredHeaders = [
            'numero_activo',
            'descripcion',
            'folio_factura',
            'fecha_factura',
            'monto_factura',
            'moneda',
            'proveedor_rfc',
            'tipo_activo_clave',
            'centro_costo_clave',
            'planta_clave',
        ];

        $optionalHeaders = [
            'uuid_cfdi',
            'ubicacion_codigo',
            'responsable_correo',
            'serie',
            'marca',
            'modelo',
            'fecha_adquisicion',
            'estatus_operativo',
            'documento_pdf',
            'documento_xml',
            'observaciones',
        ];

        $missingHeaders = array_diff($requiredHeaders, $normalizedHeaders);

        if (!empty($missingHeaders)) {
            return back()->withErrors([
                'archivo_csv' => 'El archivo no contiene los encabezados requeridos: ' . implode(', ', $missingHeaders),
            ]);
        }

        $headerIndexes = array_flip($normalizedHeaders);
        $headersToRead = array_merge($requiredHeaders, $optionalHeaders);

        $summary = [
            'procesados' => 0,
            'insertados' => 0,
            'actualizados' => 0,
            'rechazados' => 0,
            'errores' => [],
        ];

        DB::beginTransaction();

        try {
            foreach (array_slice($rows, 1) as $index => $line) {
                $lineNumber = $index + 2;
                $columns = str_getcsv($line, $delimiter);

                $data = [];

                foreach ($headersToRead as $header) {
                    $data[$header] = isset($headerIndexes[$header])
                        ? $this->normalizeCell($columns[$headerIndexes[$header]] ?? '')
                        : '';
                }

                if ($this->isEmptyCsvRow($data)) {
                    continue;
                }

                $summary['procesados']++;

                $numeroActivo = $data['numero_activo'];
                $folioFactura = $data['folio_factura'];

                $proveedorId = DB::table('proveedores')
                    ->where('rfc', $data['proveedor_rfc'])
                    ->value('id');

                if (!$proveedorId) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el RFC de proveedor {$data['proveedor_rfc']} no existe.";
                    continue;
                }

                $tipoActivoId = DB::table('tipos_activo')
                    ->where('clave', $data['tipo_activo_clave'])
                    ->value('id');

                if (!$tipoActivoId) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el tipo de activo {$data['tipo_activo_clave']} no existe.";
                    continue;
                }

                $centroCostoId = DB::table('centros_costo')
                    ->where('clave', $data['centro_costo_clave'])
                    ->value('id');

                if (!$centroCostoId) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el centro de costo {$data['centro_costo_clave']} no existe.";
                    continue;
                }

                $plantaId = DB::table('plantas')
                    ->where('clave', $data['planta_clave'])
                    ->value('id');

                if (!$plantaId) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: la planta {$data['planta_clave']} no existe.";
                    continue;
                }

                $ubicacionId = null;

                if ($data['ubicacion_codigo'] !== '') {
                    $ubicacionId = DB::table('ubicaciones')
                        ->where('codigo_interno', $data['ubicacion_codigo'])
                        ->value('id');

                    if (!$ubicacionId) {
                        $summary['rechazados']++;
                        $summary['errores'][] = "Fila {$lineNumber}: la ubicación {$data['ubicacion_codigo']} no existe.";
                        continue;
                    }
                }

                $responsableId = null;

                if ($data['responsable_correo'] !== '') {
                    $responsableId = DB::table('responsables')
                        ->where('correo', $data['responsable_correo'])
                        ->value('id');

                    if (!$responsableId) {
                        $summary['rechazados']++;
                        $summary['errores'][] = "Fila {$lineNumber}: el responsable {$data['responsable_correo']} no existe.";
                        continue;
                    }
                }

                $fechaFactura = $this->parseDate($data['fecha_factura']);

                if (!$fechaFactura) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: la fecha de factura no tiene un formato válido.";
                    continue;
                }

                $fechaAdquisicion = $this->parseDate($data['fecha_adquisicion']);

                $montoFactura = $this->toDecimal($data['monto_factura']);

                if ($montoFactura === null) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el monto de factura debe ser numérico.";
                    continue;
                }

                $moneda = $this->normalizeMoneda($data['moneda']);

                if (!$moneda) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: la moneda debe ser MXN, USD o EUR.";
                    continue;
                }

                $uuidCfdi = $data['uuid_cfdi'] !== '' ? $data['uuid_cfdi'] : null;

                $expedienteExistente = Expediente::where('numero_activo', $numeroActivo)
                    ->where('folio_factura', $folioFactura)
                    ->first();

                if ($uuidCfdi) {
                    $uuidConflict = Expediente::where('uuid_cfdi', $uuidCfdi)
                        ->when($expedienteExistente, function ($query) use ($expedienteExistente) {
                            $query->where('id', '<>', $expedienteExistente->id);
                        })
                        ->exists();

                    if ($uuidConflict) {
                        $summary['rechazados']++;
                        $summary['errores'][] = "Fila {$lineNumber}: el UUID CFDI {$uuidCfdi} ya está registrado en otro expediente.";
                        continue;
                    }
                }

                $documentos = $this->prepareDocumentReferences(
                    numeroActivo: $numeroActivo,
                    folioFactura: $folioFactura,
                    pdfName: $data['documento_pdf'],
                    xmlName: $data['documento_xml']
                );

                $estatusDocumental = $this->resolveDocumentStatus($documentos);
                $estatusOperativo = $this->normalizeEstatusOperativo($data['estatus_operativo']);

                $activoAntes = Activo::where('numero_activo', $numeroActivo)->first();
                $expedienteAntes = $expedienteExistente ? $expedienteExistente->toArray() : null;

                $activo = Activo::updateOrCreate(
                    ['numero_activo' => $numeroActivo],
                    [
                        'tipo_activo_id' => $tipoActivoId,
                        'proveedor_id' => $proveedorId,
                        'centro_costo_id' => $centroCostoId,
                        'planta_id' => $plantaId,
                        'ubicacion_id' => $ubicacionId,
                        'responsable_id' => $responsableId,
                        'descripcion' => $data['descripcion'],
                        'serie' => $data['serie'] ?: null,
                        'marca' => $data['marca'] ?: null,
                        'modelo' => $data['modelo'] ?: null,
                        'fecha_adquisicion' => $fechaAdquisicion,
                        'estatus_operativo' => $estatusOperativo,
                        'estatus_documental' => $estatusDocumental,
                        'activo' => true,
                        'creado_por' => $activoAntes ? $activoAntes->creado_por : auth()->id(),
                        'actualizado_por' => auth()->id(),
                    ]
                );

                $expediente = Expediente::updateOrCreate(
                    [
                        'numero_activo' => $numeroActivo,
                        'folio_factura' => $folioFactura,
                    ],
                    [
                        'uuid_cfdi' => $uuidCfdi,
                        'fecha_factura' => $fechaFactura,
                        'monto_factura' => $montoFactura,
                        'moneda' => $moneda,
                        'estatus' => $estatusDocumental,
                        'observaciones' => $data['observaciones'] ?: null,
                        'creado_por' => $expedienteExistente ? $expedienteExistente->creado_por : auth()->id(),
                        'actualizado_por' => auth()->id(),
                    ]
                );

                foreach ($documentos as $doc) {
                    DocumentoExpediente::updateOrCreate(
                        [
                            'expediente_id' => $expediente->id,
                            'tipo_documento' => $doc['tipo_documento'],
                        ],
                        [
                            'nombre_archivo' => $doc['nombre_archivo'],
                            'ruta_archivo' => $doc['ruta_archivo'],
                            'mime_type' => $doc['mime_type'],
                            'tamano_bytes' => null,
                            'hash_sha256' => null,
                            'version' => 1,
                            'vigente' => true,
                            'cargado_por' => auth()->id(),
                        ]
                    );
                }

                if ($expediente->wasRecentlyCreated) {
                    $summary['insertados']++;
                    $accion = 'IMPORTACION_EXPEDIENTE_ALTA';
                } else {
                    $summary['actualizados']++;
                    $accion = 'IMPORTACION_EXPEDIENTE_ACTUALIZACION';
                }

                $this->registrarBitacora(
                    numeroActivo: $numeroActivo,
                    accion: $accion,
                    tablaAfectada: 'expedientes',
                    registroClave: (string) $expediente->id,
                    antes: [
                        'activo' => $activoAntes ? $activoAntes->toArray() : null,
                        'expediente' => $expedienteAntes,
                    ],
                    despues: [
                        'activo' => $activo->fresh()->toArray(),
                        'expediente' => $expediente->fresh()->toArray(),
                        'documentos' => $documentos,
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            return back()->withErrors([
                'archivo_csv' => 'Ocurrió un error durante la importación: ' . $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('registro-masivo')
            ->with('success', 'La carga masiva de expedientes fue procesada correctamente.')
            ->with('import_summary', $summary);
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
                'factura_184.pdf',
                'factura_184.xml',
                'Carga masiva de expediente de activo fijo.',
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

    private function prepareDocumentReferences(
        string $numeroActivo,
        string $folioFactura,
        ?string $pdfName,
        ?string $xmlName
    ): array {
        $docs = [];

        $basePath = 'carga_masiva/referencias/'
            . Str::slug($numeroActivo)
            . '/'
            . Str::slug($folioFactura);

        if ($pdfName) {
            $docs[] = [
                'tipo_documento' => 'PDF',
                'nombre_archivo' => $pdfName,
                'ruta_archivo' => $basePath . '/' . $this->safeFileName($pdfName, 'pdf'),
                'mime_type' => 'application/pdf',
            ];
        }

        if ($xmlName) {
            $docs[] = [
                'tipo_documento' => 'XML',
                'nombre_archivo' => $xmlName,
                'ruta_archivo' => $basePath . '/' . $this->safeFileName($xmlName, 'xml'),
                'mime_type' => 'application/xml',
            ];
        }

        return $docs;
    }

    private function resolveDocumentStatus(array $docs): string
    {
        $tipos = collect($docs)
            ->pluck('tipo_documento')
            ->unique()
            ->values()
            ->all();

        if (in_array('PDF', $tipos, true) && in_array('XML', $tipos, true)) {
            return 'completo';
        }

        return 'incompleto';
    }

    private function safeFileName(string $name, string $defaultExtension): string
    {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: $defaultExtension;

        $safeBaseName = Str::slug($baseName) ?: 'documento';

        return $safeBaseName . '.' . $extension;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($candidates);

        return array_key_first($candidates) ?: ',';
    }

    private function normalizeHeader(?string $value): string
    {
        $value = $this->normalizeCell($value);
        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim($value, '_');

        return $value;
    }

    private function normalizeCell(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);

        return $value;
    }

    private function isEmptyCsvRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function toDecimal(?string $value): ?float
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(['$', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
            $parts = explode(',', $value);
            $lastPart = end($parts);

            if (strlen($lastPart) === 2) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseDate(?string $value): ?string
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return null;
        }

        $formats = [
            'd/m/Y',
            'Y-m-d',
            'd-m-Y',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                //
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function normalizeMoneda(?string $value): ?string
    {
        $value = strtoupper($this->normalizeCell($value));

        return in_array($value, ['MXN', 'USD', 'EUR'], true) ? $value : null;
    }

    private function normalizeEstatusOperativo(?string $value): string
    {
        $value = $this->normalizeHeader($value);

        return match ($value) {
            'baja' => 'baja',
            'traslado' => 'traslado',
            'en_operacion', 'operacion', 'activo' => 'en_operacion',
            default => 'en_operacion',
        };
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => auth()->id(),
            'modulo' => 'M01 Gestión de expedientes de activo fijo',
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_clave' => $registroClave,
            'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
