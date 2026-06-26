<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportValoresActivoRequest;
use App\Http\Requests\StoreValorActivoRequest;
use App\Models\ValorActivo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValoresActivoController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $resultados = $query
            ->orderByDesc('v.fecha_corte')
            ->orderByDesc('v.id')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        $valorEdit = null;

        if ($request->filled('editar_valor')) {
            $valorEdit = $this->findValorForEdit((int) $request->input('editar_valor'));
        }

        return view('swafi.valores', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
            'valorEdit' => $valorEdit,
        ]);
    }

    public function store(StoreValorActivoRequest $request)
    {
        $data = $request->validated();

        $valorEnLibros = $data['valor_en_libros'] ?? null;

        if ($valorEnLibros === null || $valorEnLibros === '') {
            $valorEnLibros = max(
                0,
                (float) $data['valor_fiscal'] - (float) $data['depreciacion_acumulada']
            );
        }

        $payload = [
            'numero_activo' => $data['numero_activo'],
            'valor_fiscal' => $data['valor_fiscal'],
            'valor_financiero' => $data['valor_financiero'],
            'depreciacion_acumulada' => $data['depreciacion_acumulada'],
            'valor_en_libros' => $valorEnLibros,
            'vida_util_meses' => $data['vida_util_meses'] ?? null,
            'estatus_contable' => $data['estatus_contable'],
            'fecha_corte' => $data['fecha_corte'],
            'registrado_por' => auth()->id(),
        ];

        if (!empty($data['valor_id'])) {
            $valor = ValorActivo::findOrFail($data['valor_id']);
            $antes = $valor->toArray();

            $valor->update($payload);

            $this->registrarBitacora(
                numeroActivo: $valor->numero_activo,
                accion: 'EDICION_VALOR',
                registroClave: (string) $valor->id,
                antes: $antes,
                despues: $valor->fresh()->toArray()
            );

            return redirect()
                ->route('valores')
                ->with('success', 'Los valores fiscales y financieros se actualizaron correctamente.');
        }

        $valor = ValorActivo::create($payload);

        $this->registrarBitacora(
            numeroActivo: $valor->numero_activo,
            accion: 'ALTA_VALOR',
            registroClave: (string) $valor->id,
            antes: null,
            despues: $valor->toArray()
        );

        return redirect()
            ->route('valores')
            ->with('success', 'Los valores fiscales y financieros se registraron correctamente.');
    }

    public function importar(ImportValoresActivoRequest $request)
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
            'valor_fiscal',
            'depreciacion_acumulada',
            'valor_en_libros',
            'valor_financiero',
            'vida_util_meses',
            'fecha_corte',
            'estatus_contable',
        ];

        $missingHeaders = array_diff($requiredHeaders, $normalizedHeaders);

        if (!empty($missingHeaders)) {
            return back()->withErrors([
                'archivo_csv' => 'El archivo no contiene los encabezados requeridos: ' . implode(', ', $missingHeaders),
            ]);
        }

        $headerIndexes = array_flip($normalizedHeaders);

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

                foreach ($requiredHeaders as $header) {
                    $data[$header] = $this->normalizeCell($columns[$headerIndexes[$header]] ?? '');
                }

                if ($this->isEmptyCsvRow($data)) {
                    continue;
                }

                $summary['procesados']++;

                $numeroActivo = $data['numero_activo'];

                $activoExists = DB::table('activos')
                    ->where('numero_activo', $numeroActivo)
                    ->exists();

                if (!$activoExists) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el activo {$numeroActivo} no existe en SWAFI.";
                    continue;
                }

                $valorFiscal = $this->toDecimal($data['valor_fiscal']);
                $depreciacionAcumulada = $this->toDecimal($data['depreciacion_acumulada']);
                $valorEnLibros = $this->toDecimal($data['valor_en_libros']);
                $valorFinanciero = $this->toDecimal($data['valor_financiero']);
                $vidaUtilMeses = $this->toInteger($data['vida_util_meses']);
                $fechaCorte = $this->parseFechaCorte($data['fecha_corte']);
                $estatusContable = $this->normalizeEstatusContable($data['estatus_contable']);

                if ($valorFiscal === null || $depreciacionAcumulada === null || $valorFinanciero === null) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: los valores fiscales y financieros deben ser numéricos.";
                    continue;
                }

                if ($valorEnLibros === null) {
                    $valorEnLibros = max(0, $valorFiscal - $depreciacionAcumulada);
                }

                if ($vidaUtilMeses === null || $vidaUtilMeses <= 0) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: la vida útil debe ser un número entero mayor a cero.";
                    continue;
                }

                if (!$fechaCorte) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: la fecha de corte no tiene un formato válido.";
                    continue;
                }

                if (!in_array($estatusContable, ['vigente', 'en_revision', 'baja'], true)) {
                    $summary['rechazados']++;
                    $summary['errores'][] = "Fila {$lineNumber}: el estatus contable no es válido.";
                    continue;
                }

                $registroExistente = ValorActivo::where('numero_activo', $numeroActivo)
                    ->whereDate('fecha_corte', $fechaCorte)
                    ->first();

                $antes = $registroExistente ? $registroExistente->toArray() : null;

                $payload = [
                    'valor_fiscal' => $valorFiscal,
                    'valor_financiero' => $valorFinanciero,
                    'depreciacion_acumulada' => $depreciacionAcumulada,
                    'valor_en_libros' => $valorEnLibros,
                    'vida_util_meses' => $vidaUtilMeses,
                    'estatus_contable' => $estatusContable,
                    'registrado_por' => auth()->id(),
                ];

                $valor = ValorActivo::updateOrCreate(
                    [
                        'numero_activo' => $numeroActivo,
                        'fecha_corte' => $fechaCorte,
                    ],
                    $payload
                );

                if ($valor->wasRecentlyCreated) {
                    $summary['insertados']++;
                    $accion = 'IMPORTACION_VALOR_ALTA';
                } else {
                    $summary['actualizados']++;
                    $accion = 'IMPORTACION_VALOR_ACTUALIZACION';
                }

                $this->registrarBitacora(
                    numeroActivo: $valor->numero_activo,
                    accion: $accion,
                    registroClave: (string) $valor->id,
                    antes: $antes,
                    despues: $valor->fresh()->toArray()
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
            ->route('valores')
            ->with('success', 'La carga masiva de valores fue procesada correctamente.')
            ->with('import_summary', $summary);
    }

    public function plantillaCsv()
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');

            /*
            |--------------------------------------------------------------------------
            | BOM UTF-8
            |--------------------------------------------------------------------------
            | Ayuda a que Excel reconozca caracteres especiales correctamente.
            */

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Numero activo',
                'Valor fiscal',
                'Depreciacion acumulada',
                'Valor en libros',
                'Valor financiero',
                'Vida util meses',
                'Fecha corte',
                'Estatus contable',
            ]);

            fputcsv($output, [
                'BIM-537028',
                '602700',
                '10045',
                '592655',
                '602700',
                '60',
                '25/06/2026',
                'vigente',
            ]);

            fclose($output);
        }, 'plantilla_valores_activo_swafi.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(int $valor)
    {
        $registro = ValorActivo::findOrFail($valor);
        $antes = $registro->toArray();
        $numeroActivo = $registro->numero_activo;

        $registro->delete();

        $this->registrarBitacora(
            numeroActivo: $numeroActivo,
            accion: 'ELIMINACION_VALOR',
            registroClave: (string) $valor,
            antes: $antes,
            despues: null
        );

        return redirect()
            ->route('valores')
            ->with('success', 'El registro de valores fue eliminado correctamente.');
    }

    private function baseQuery()
    {
        $latestExpedientes = DB::table('expedientes')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        return DB::table('valores_activo as v')
            ->join('activos as a', 'a.numero_activo', '=', 'v.numero_activo')
            ->leftJoinSub($latestExpedientes, 'le', function ($join) {
                $join->on('le.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->select([
                'v.id as valor_id',
                'v.numero_activo',
                'v.valor_fiscal',
                'v.valor_financiero',
                'v.depreciacion_acumulada',
                'v.valor_en_libros',
                'v.vida_util_meses',
                'v.estatus_contable',
                'v.fecha_corte',
                'v.created_at',
                'e.folio_factura',
                'e.uuid_cfdi',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'p.id as proveedor_id',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'cc.id as centro_costo_id',
                'cc.clave as centro_costo_clave',
                'cc.descripcion as centro_costo_descripcion',
                'pl.id as planta_id',
                'pl.nombre as planta_nombre',
                'ta.id as tipo_activo_id',
                'ta.descripcion as tipo_activo',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('numero_activo')) {
            $query->where('v.numero_activo', 'like', '%' . $request->numero_activo . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', $request->planta_id);
        }

        if ($request->filled('proveedor_id')) {
            $query->where('a.proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('centro_costo_id')) {
            $query->where('a.centro_costo_id', $request->centro_costo_id);
        }

        if ($request->filled('tipo_activo_id')) {
            $query->where('a.tipo_activo_id', $request->tipo_activo_id);
        }

        if ($request->filled('estatus_contable')) {
            $query->where('v.estatus_contable', $request->estatus_contable);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('v.fecha_corte', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('v.fecha_corte', '<=', $request->fecha_hasta);
        }

        if ($request->filled('valor_desde')) {
            $query->where('v.valor_fiscal', '>=', $request->valor_desde);
        }

        if ($request->filled('valor_hasta')) {
            $query->where('v.valor_fiscal', '<=', $request->valor_hasta);
        }
    }

    private function catalogos(): array
    {
        return [
            'activos' => DB::table('activos')
                ->select('numero_activo', 'descripcion')
                ->orderBy('numero_activo')
                ->get(),

            'plantas' => DB::table('plantas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'proveedores' => DB::table('proveedores')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'centrosCosto' => DB::table('centros_costo')
                ->where('estatus', 'activo')
                ->orderBy('clave')
                ->get(),

            'tiposActivo' => DB::table('tipos_activo')
                ->where('estatus', 'activo')
                ->orderBy('descripcion')
                ->get(),
        ];
    }

    private function findValorForEdit(int $id)
    {
        return $this->baseQuery()
            ->where('v.id', $id)
            ->first();
    }

    private function exportCsv($query)
    {
        $rows = $query
            ->orderByDesc('v.fecha_corte')
            ->orderByDesc('v.id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Numero activo',
                'Folio factura',
                'Proveedor',
                'Planta',
                'Centro costo',
                'Tipo activo',
                'Valor fiscal',
                'Depreciacion acumulada',
                'Valor en libros',
                'Valor financiero',
                'Vida util meses',
                'Fecha corte',
                'Estatus contable',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->numero_activo,
                    $row->folio_factura,
                    $row->proveedor_nombre,
                    $row->planta_nombre,
                    $row->centro_costo_clave,
                    $row->tipo_activo,
                    $row->valor_fiscal,
                    $row->depreciacion_acumulada,
                    $row->valor_en_libros,
                    $row->valor_financiero,
                    $row->vida_util_meses,
                    $row->fecha_corte,
                    $row->estatus_contable,
                ]);
            }

            fclose($output);
        }, 'valores_activo_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

        /*
        |--------------------------------------------------------------------------
        | Normalización de separadores numéricos
        |--------------------------------------------------------------------------
        | Soporta:
        | 602700
        | 602700.50
        | 602700,50
        | 602,700.50
        | 602,700
        */

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

    private function toInteger(?string $value): ?int
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }

    private function parseFechaCorte(?string $value): ?string
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

    private function normalizeEstatusContable(?string $value): string
    {
        $value = $this->normalizeHeader($value);

        return match ($value) {
            'en_revision', 'revision', 'en_revicion' => 'en_revision',
            'baja', 'dado_de_baja' => 'baja',
            default => 'vigente',
        };
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => auth()->id(),
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
            'accion' => $accion,
            'tabla_afectada' => 'valores_activo',
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
