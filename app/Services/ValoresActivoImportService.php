<?php

namespace App\Services;

use App\Models\ImportacionValores;
use App\Models\ImportacionValoresFila;
use App\Models\ValorActivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ValoresActivoImportService
{
    /** @var list<string> */
    public const REQUIRED_HEADERS = [
        'numero_activo',
        'valor_fiscal',
        'depreciacion_acumulada',
        'valor_en_libros',
        'valor_financiero',
        'vida_util_meses',
        'fecha_corte',
        'estatus_contable',
    ];

    /** @var array<string, string> */
    public const TEMPLATE_HEADERS = [
        'numero_activo' => 'Numero activo',
        'valor_fiscal' => 'Valor fiscal',
        'depreciacion_acumulada' => 'Depreciacion acumulada',
        'valor_en_libros' => 'Valor en libros',
        'valor_financiero' => 'Valor financiero',
        'moneda' => 'Moneda',
        'tipo_cambio' => 'Tipo cambio',
        'fecha_tipo_cambio' => 'Fecha tipo cambio',
        'origen_tipo_cambio' => 'Origen tipo cambio',
        'vida_util_meses' => 'Vida util meses',
        'fecha_corte' => 'Fecha corte',
        'estatus_contable' => 'Estatus contable',
        'motivo_cambio' => 'Motivo cambio',
    ];

    /** @var array<string, string> */
    public const HEADER_ALIASES = [
        'depreciacion_acumulada_oracle_erp' => 'depreciacion_acumulada',
        'valor_en_libros_oracle_erp' => 'valor_en_libros',
        'vida_util_oficial_meses' => 'vida_util_meses',
    ];

    public function __construct(
        private readonly CfdiValidationService $cfdiService
    ) {
    }

    public function previsualizar(UploadedFile $file, int $userId): ImportacionValores
    {
        $path = $file->getRealPath();

        if (!is_string($path) || $path === '' || !is_file($path)) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'No fue posible leer el archivo seleccionado.',
            ]);
        }

        $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!is_array($rows) || count($rows) < 2) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo no contiene registros para previsualizar.',
            ]);
        }

        $maxRows = min(
            20000,
            max(1, (int) config('swafi.valores.importacion_max_filas', 5000))
        );

        if (count($rows) - 1 > $maxRows) {
            throw ValidationException::withMessages([
                'archivo_csv' => "El archivo supera el máximo permitido de {$maxRows} filas.",
            ]);
        }

        $delimiter = self::detectDelimiter((string) $rows[0]);
        $headers = self::normalizeImportHeaders(str_getcsv((string) $rows[0], $delimiter));
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'Faltan encabezados obligatorios: ' . implode(', ', $missing),
            ]);
        }

        $duplicates = collect(array_count_values(array_values(array_filter(
            $headers,
            static fn (string $header): bool => $header !== ''
        ))))
            ->filter(static fn (int $count): bool => $count > 1)
            ->keys()
            ->values()
            ->all();

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'Existen encabezados duplicados después de normalizarlos: ' . implode(', ', $duplicates),
            ]);
        }

        $indexes = array_flip($headers);
        [$currencyRules, $statusKeys] = $this->catalogRules();
        $seenAssets = [];
        $stagedRows = [];

        foreach (array_slice($rows, 1) as $index => $line) {
            $lineNumber = $index + 2;
            $columns = str_getcsv((string) $line, $delimiter);
            $get = static fn (string $key): string => self::normalizeCell(
                $columns[$indexes[$key] ?? -1] ?? ''
            );
            $numeroActivo = mb_strtoupper($get('numero_activo'), 'UTF-8');
            $errors = [];

            $payload = [
                'numero_activo' => $numeroActivo,
                'valor_fiscal' => self::toDecimal($get('valor_fiscal')),
                'depreciacion_acumulada' => self::toDecimal($get('depreciacion_acumulada')),
                'valor_en_libros' => self::toDecimal($get('valor_en_libros')),
                'valor_financiero' => self::toDecimal($get('valor_financiero')),
                'vida_util_meses' => self::toInteger($get('vida_util_meses')),
                'fecha_corte' => self::parseDate($get('fecha_corte')),
                'estatus_contable' => self::normalizeStatus($get('estatus_contable')),
                'moneda' => mb_strtoupper($get('moneda') ?: 'MXN', 'UTF-8'),
                'tipo_cambio' => self::toDecimal($get('tipo_cambio'), 6),
                'fecha_tipo_cambio' => self::parseDate($get('fecha_tipo_cambio')),
                'origen_tipo_cambio' => $get('origen_tipo_cambio') ?: null,
                'motivo_cambio' => $get('motivo_cambio') ?: 'Actualización mediante carga masiva.',
            ];

            if ($numeroActivo === '') {
                $errors[] = 'El número de activo es obligatorio.';
            } elseif (isset($seenAssets[$numeroActivo])) {
                $errors[] = "El activo {$numeroActivo} está repetido en el archivo; conserva una sola fila por activo.";
            } else {
                $seenAssets[$numeroActivo] = true;
            }

            $stagedRows[] = [
                'numero_fila' => $lineNumber,
                'numero_activo' => $numeroActivo,
                'payload' => $payload,
                'errores' => $errors,
            ];
        }

        $assetNumbers = array_values(array_filter(array_keys($seenAssets)));
        $existingAssetNumbers = $this->existingAssetNumbers($assetNumbers);
        $existingValues = $this->existingValuesByAsset($assetNumbers);
        $previewRows = [];

        foreach ($stagedRows as $stagedRow) {
            $numeroActivo = (string) $stagedRow['numero_activo'];
            /** @var array<string, mixed> $payload */
            $payload = $stagedRow['payload'];
            /** @var array<int, string> $errors */
            $errors = $stagedRow['errores'];
            $assetExists = $numeroActivo !== '' && isset($existingAssetNumbers[$numeroActivo]);

            if ($numeroActivo !== '' && !$assetExists) {
                $errors[] = "El activo {$numeroActivo} no existe.";
            }

            if (array_key_exists($payload['moneda'], $currencyRules) && !$currencyRules[$payload['moneda']]) {
                $payload['tipo_cambio'] = 1.0;
                $payload['fecha_tipo_cambio'] = null;
                $payload['origen_tipo_cambio'] = null;
            }

            $validationError = self::validateImportPayload($payload, $currencyRules, $statusKeys);

            if ($validationError !== null) {
                $errors[] = ucfirst($validationError);
            }

            $existing = $assetExists ? ($existingValues[$numeroActivo] ?? null) : null;
            $action = $existing
                ? ($existing->trashed() ? 'restaurar' : 'actualizar')
                : ($assetExists ? 'insertar' : null);

            $previewRows[] = [
                'numero_fila' => (int) $stagedRow['numero_fila'],
                'numero_activo' => $numeroActivo ?: null,
                'estatus' => $errors === [] ? 'correcta' : 'incorrecta',
                'accion' => $errors === [] ? $action : null,
                'datos' => [
                    'payload' => $payload,
                    'baseline' => $this->baselineFor($existing),
                ],
                'errores' => $errors,
            ];
        }

        if ($previewRows === []) {
            throw ValidationException::withMessages([
                'archivo_csv' => 'El archivo no contiene filas con información para previsualizar.',
            ]);
        }

        $correct = collect($previewRows)->where('estatus', 'correcta')->count();
        $incorrect = count($previewRows) - $correct;
        $hash = hash_file('sha256', $path);

        if (!is_string($hash) || strlen($hash) !== 64) {
            throw new RuntimeException('No fue posible calcular la huella SHA-256 del archivo de valores.');
        }

        return DB::transaction(function () use (
            $file,
            $userId,
            $previewRows,
            $correct,
            $incorrect,
            $hash
        ): ImportacionValores {
            $batch = ImportacionValores::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'estado' => 'previsualizada',
                'archivo_nombre_original' => mb_substr($file->getClientOriginalName(), 0, 255),
                'archivo_hash_sha256' => $hash,
                'total_filas' => count($previewRows),
                'filas_correctas' => $correct,
                'filas_incorrectas' => $incorrect,
                'resumen' => [
                    'previsualizacion' => [
                        'correctas' => $correct,
                        'incorrectas' => $incorrect,
                    ],
                ],
                'expira_at' => now()->addHours($this->previewHours()),
            ]);

            $timestamp = now();

            foreach (array_chunk($previewRows, 500) as $rowChunk) {
                $records = array_map(static function (array $row) use ($batch, $timestamp): array {
                    return [
                        'importacion_id' => $batch->id,
                        'numero_fila' => $row['numero_fila'],
                        'numero_activo' => $row['numero_activo'],
                        'estatus' => $row['estatus'],
                        'accion' => $row['accion'],
                        'datos' => json_encode(
                            $row['datos'],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                        'errores' => json_encode(
                            $row['errores'],
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                        'aplicada' => false,
                        'resultado' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }, $rowChunk);

                DB::table('importacion_valores_filas')->insert($records);
            }

            $this->registerAudit(
                userId: $userId,
                asset: null,
                action: 'PREVISUALIZACION_VALORES',
                table: 'importaciones_valores',
                key: $batch->uuid,
                before: null,
                after: [
                    'estado' => 'previsualizada',
                    'archivo' => $batch->archivo_nombre_original,
                    'total_filas' => $batch->total_filas,
                    'filas_correctas' => $batch->filas_correctas,
                    'filas_incorrectas' => $batch->filas_incorrectas,
                ]
            );

            return $batch->fresh();
        });
    }

    /**
     * @return array{procesados:int,insertados:int,actualizados:int,restaurados:int,rechazados:int,errores:array<int,string>}
     */
    public function aplicar(ImportacionValores $batch, int $userId): array
    {
        return DB::transaction(function () use ($batch, $userId): array {
            $locked = ImportacionValores::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwnedBy($locked, $userId);

            if ($locked->estado !== 'previsualizada') {
                throw ValidationException::withMessages([
                    'lote' => 'La previsualización ya fue aplicada, cancelada o dejó de estar disponible.',
                ]);
            }

            if ($locked->expira_at?->isPast()) {
                throw ValidationException::withMessages([
                    'lote' => 'La previsualización venció. Genera una nueva antes de aplicar cambios.',
                ]);
            }

            $rows = ImportacionValoresFila::query()
                ->where('importacion_id', $locked->id)
                ->where('estatus', 'correcta')
                ->where('aplicada', false)
                ->orderBy('numero_fila')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'lote' => 'La previsualización no contiene filas correctas pendientes de aplicación.',
                ]);
            }

            [$currencyRules, $statusKeys] = $this->catalogRules();
            $stagedApplyRows = [];
            $assetNumbers = [];

            foreach ($rows as $row) {
                $data = is_array($row->datos) ? $row->datos : [];
                $payload = data_get($data, 'payload');

                if (!is_array($payload)) {
                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} no contiene datos válidos. Genera una nueva previsualización.",
                    ]);
                }

                $numeroActivo = (string) ($payload['numero_activo'] ?? '');

                if ($numeroActivo === '') {
                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} dejó de ser válida porque no contiene número de activo.",
                    ]);
                }

                $assetNumbers[] = $numeroActivo;
                $stagedApplyRows[] = [
                    'row' => $row,
                    'data' => $data,
                    'payload' => $payload,
                    'numero_activo' => $numeroActivo,
                ];
            }

            $existingAssetNumbers = $this->existingAssetNumbers($assetNumbers, true);
            $existingValues = $this->existingValuesByAsset($assetNumbers, true);
            $validated = [];

            foreach ($stagedApplyRows as $stagedApplyRow) {
                /** @var ImportacionValoresFila $row */
                $row = $stagedApplyRow['row'];
                /** @var array<string, mixed> $data */
                $data = $stagedApplyRow['data'];
                /** @var array<string, mixed> $payload */
                $payload = $stagedApplyRow['payload'];
                $numeroActivo = (string) $stagedApplyRow['numero_activo'];

                if (!isset($existingAssetNumbers[$numeroActivo])) {
                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} dejó de ser válida porque el activo ya no existe.",
                    ]);
                }

                $validationError = self::validateImportPayload($payload, $currencyRules, $statusKeys);

                if ($validationError !== null) {
                    throw ValidationException::withMessages([
                        'lote' => "La fila {$row->numero_fila} dejó de cumplir las reglas vigentes: {$validationError}",
                    ]);
                }

                $existing = $existingValues[$numeroActivo] ?? null;
                $currentAction = $existing
                    ? ($existing->trashed() ? 'restaurar' : 'actualizar')
                    : 'insertar';

                if ($currentAction !== $row->accion || !$this->baselineMatches(data_get($data, 'baseline'), $existing)) {
                    throw ValidationException::withMessages([
                        'lote' => "Los valores del activo {$numeroActivo} cambiaron después de la previsualización. Genera una nueva antes de aplicar.",
                    ]);
                }

                $validated[] = [
                    'row' => $row,
                    'payload' => $payload,
                    'existing' => $existing,
                ];
            }

            $summary = [
                'procesados' => count($validated),
                'insertados' => 0,
                'actualizados' => 0,
                'restaurados' => 0,
                'rechazados' => (int) $locked->filas_incorrectas,
                'errores' => [],
            ];

            foreach ($validated as $item) {
                /** @var ImportacionValoresFila $row */
                $row = $item['row'];
                /** @var array<string, mixed> $payload */
                $payload = $item['payload'];
                /** @var ValorActivo|null $existing */
                $existing = $item['existing'];
                $numeroActivo = (string) $payload['numero_activo'];
                $payload['registrado_por'] = $userId;

                $reconciliation = $this->cfdiService->reconcileValuePayload($numeroActivo, $payload);
                $payload['cfdi_validacion_id'] = $reconciliation['validation_id'];
                $payload['conciliacion_cfdi'] = $reconciliation['status'];
                $payload['conciliacion_detalle'] = $reconciliation['details'];

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
                    $actionKey = $wasDeleted ? 'restaurados' : 'actualizados';
                    $summary[$actionKey]++;
                    $auditAction = $wasDeleted
                        ? 'IMPORTACION_VALOR_RESTAURACION'
                        : 'IMPORTACION_VALOR_EDICION';
                    $record = $existing->fresh();

                    $this->registerAudit(
                        userId: $userId,
                        asset: $numeroActivo,
                        action: $auditAction,
                        table: 'valores_activo',
                        key: (string) $existing->id,
                        before: $before,
                        after: $record?->toArray()
                    );
                } else {
                    $record = ValorActivo::create($payload);
                    $summary['insertados']++;

                    $this->registerAudit(
                        userId: $userId,
                        asset: $numeroActivo,
                        action: 'IMPORTACION_VALOR_ALTA',
                        table: 'valores_activo',
                        key: (string) $record->id,
                        before: null,
                        after: $record->toArray()
                    );
                }

                $row->update([
                    'aplicada' => true,
                    'resultado' => [
                        'accion' => $row->accion,
                        'valor_id' => $record?->id,
                        'aplicada_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            $locked->update([
                'estado' => 'aplicada',
                'filas_insertadas' => $summary['insertados'],
                'filas_actualizadas' => $summary['actualizados'],
                'filas_restauradas' => $summary['restaurados'],
                'aplicada_at' => now(),
                'resumen' => array_merge($locked->resumen ?? [], [
                    'aplicacion' => $summary,
                ]),
            ]);

            $this->registerAudit(
                userId: $userId,
                asset: null,
                action: 'APLICACION_IMPORTACION_VALORES',
                table: 'importaciones_valores',
                key: $locked->uuid,
                before: ['estado' => 'previsualizada'],
                after: ['estado' => 'aplicada', 'resumen' => $summary]
            );

            return $summary;
        });
    }

    public function cancelar(ImportacionValores $batch, int $userId): void
    {
        DB::transaction(function () use ($batch, $userId): void {
            $locked = ImportacionValores::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwnedBy($locked, $userId);

            if ($locked->estado !== 'previsualizada') {
                throw ValidationException::withMessages([
                    'lote' => 'Solo se puede cancelar una previsualización pendiente.',
                ]);
            }

            $locked->update([
                'estado' => 'cancelada',
                'cancelada_at' => now(),
            ]);

            $this->registerAudit(
                userId: $userId,
                asset: null,
                action: 'CANCELACION_IMPORTACION_VALORES',
                table: 'importaciones_valores',
                key: $locked->uuid,
                before: ['estado' => 'previsualizada'],
                after: ['estado' => 'cancelada']
            );
        });
    }

    public static function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param array<int, string|null> $headers
     * @return list<string>
     */
    public static function normalizeImportHeaders(array $headers): array
    {
        return array_values(array_map(static function (?string $header): string {
            $normalized = self::normalizeHeader($header);

            return self::HEADER_ALIASES[$normalized] ?? $normalized;
        }, $headers));
    }

    public static function normalizeHeader(?string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    public static function normalizeCell(?string $value): string
    {
        return trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value));
    }

    public static function toDecimal(?string $value, int $scale = 2): ?float
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

    public static function toInteger(?string $value): ?int
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : null;
    }

    public static function parseDate(?string $value): ?string
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

    public static function normalizeStatus(?string $value): ?string
    {
        $value = self::normalizeHeader($value);

        return match ($value) {
            'vigente', 'activo' => 'vigente',
            'en_revision', 'revision', 'en_revicion' => 'en_revision',
            'baja', 'dado_de_baja' => 'baja',
            default => $value !== '' ? $value : null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, bool> $currencyRules
     * @param array<int, string> $statusKeys
     */
    public static function validateImportPayload(
        array $payload,
        array $currencyRules,
        array $statusKeys
    ): ?string {
        if (!in_array((string) ($payload['estatus_contable'] ?? ''), $statusKeys, true)) {
            return 'el estatus contable no existe o se encuentra inactivo.';
        }

        if (empty($payload['fecha_corte'])) {
            return 'la fecha de corte no es válida.';
        }

        $life = $payload['vida_util_meses'] ?? null;

        if (!$life || (int) $life <= 0 || (int) $life > 1200) {
            return 'la vida útil debe ser un entero entre 1 y 1200 meses.';
        }

        foreach ([
            'valor_fiscal' => 'valor fiscal',
            'valor_financiero' => 'valor financiero',
            'depreciacion_acumulada' => 'depreciación acumulada',
            'valor_en_libros' => 'valor en libros',
        ] as $key => $label) {
            if (!array_key_exists($key, $payload) || $payload[$key] === null || (float) $payload[$key] < 0) {
                return "el {$label} debe ser numérico y no puede ser negativo.";
            }
        }

        if (
            ($payload['estatus_contable'] ?? null) !== 'baja'
            && ((float) $payload['valor_fiscal'] <= 0 || (float) $payload['valor_financiero'] <= 0)
        ) {
            return 'un activo vigente o en revisión requiere valor fiscal y valor financiero mayores a cero.';
        }

        $currency = (string) ($payload['moneda'] ?? '');

        if (!array_key_exists($currency, $currencyRules)) {
            return 'la moneda no existe o se encuentra inactiva.';
        }

        if (
            $currencyRules[$currency]
            && (
                empty($payload['tipo_cambio'])
                || empty($payload['fecha_tipo_cambio'])
                || empty($payload['origen_tipo_cambio'])
            )
        ) {
            return 'la moneda seleccionada requiere tipo de cambio, fecha y origen.';
        }

        return null;
    }

    /** @return array{0:array<string,bool>,1:array<int,string>} */
    private function catalogRules(): array
    {
        $currencyRules = DB::table('monedas')
            ->where('estatus', 'activo')
            ->pluck('requiere_tipo_cambio', 'clave')
            ->mapWithKeys(static fn (mixed $required, mixed $key): array => [
                mb_strtoupper((string) $key, 'UTF-8') => (bool) $required,
            ])
            ->all();
        $statusKeys = DB::table('estatus_contables')
            ->where('estatus', 'activo')
            ->pluck('clave')
            ->map(static fn (mixed $key): string => mb_strtolower((string) $key, 'UTF-8'))
            ->all();

        return [$currencyRules, $statusKeys];
    }

    /**
     * @param array<int, string> $assetNumbers
     * @return array<string, bool>
     */
    private function existingAssetNumbers(array $assetNumbers, bool $lockForUpdate = false): array
    {
        $uniqueNumbers = array_values(array_unique(array_filter($assetNumbers)));
        sort($uniqueNumbers, SORT_STRING);
        $existing = [];

        foreach (array_chunk($uniqueNumbers, 1000) as $chunk) {
            $query = DB::table('activos')->whereIn('numero_activo', $chunk);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            foreach ($query->pluck('numero_activo') as $numeroActivo) {
                $existing[(string) $numeroActivo] = true;
            }
        }

        return $existing;
    }

    /**
     * @param array<int, string> $assetNumbers
     * @return array<string, ValorActivo>
     */
    private function existingValuesByAsset(array $assetNumbers, bool $lockForUpdate = false): array
    {
        $uniqueNumbers = array_values(array_unique(array_filter($assetNumbers)));
        sort($uniqueNumbers, SORT_STRING);
        $existing = [];

        foreach (array_chunk($uniqueNumbers, 1000) as $chunk) {
            $query = ValorActivo::withTrashed()->whereIn('numero_activo', $chunk);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            foreach ($query->get() as $value) {
                $existing[$value->numero_activo] = $value;
            }
        }

        return $existing;
    }

    /** @return array{id:?int,updated_at:?string,deleted_at:?string,snapshot_hash:?string} */
    private function baselineFor(?ValorActivo $record): array
    {
        return [
            'id' => $record?->id,
            'updated_at' => $record?->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $record?->deleted_at?->format('Y-m-d H:i:s'),
            'snapshot_hash' => $this->snapshotHash($record),
        ];
    }

    private function baselineMatches(mixed $baseline, ?ValorActivo $record): bool
    {
        if (!is_array($baseline)) {
            return false;
        }

        $baselineHash = (string) ($baseline['snapshot_hash'] ?? '');
        $currentHash = (string) ($this->snapshotHash($record) ?? '');

        return (string) ($baseline['id'] ?? '') === (string) ($record?->id ?? '')
            && (string) ($baseline['updated_at'] ?? '') === (string) ($record?->updated_at?->format('Y-m-d H:i:s') ?? '')
            && (string) ($baseline['deleted_at'] ?? '') === (string) ($record?->deleted_at?->format('Y-m-d H:i:s') ?? '')
            && hash_equals($baselineHash, $currentHash);
    }

    private function snapshotHash(?ValorActivo $record): ?string
    {
        if (!$record) {
            return null;
        }

        $attributes = $record->getAttributes();
        ksort($attributes);
        $encoded = json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new RuntimeException('No fue posible construir la huella del valor fiscal y financiero.');
        }

        return hash('sha256', $encoded);
    }

    private function assertOwnedBy(ImportacionValores $batch, int $userId): void
    {
        if ((int) $batch->user_id !== $userId) {
            throw ValidationException::withMessages([
                'lote' => 'La previsualización seleccionada no pertenece a tu usuario.',
            ]);
        }
    }

    private function previewHours(): int
    {
        return min(72, max(1, (int) config('swafi.valores.previsualizacion_horas', 24)));
    }

    private function registerAudit(
        int $userId,
        ?string $asset,
        string $action,
        string $table,
        string $key,
        ?array $before,
        ?array $after
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $asset,
            'user_id' => $userId,
            'modulo' => 'M02 Control fiscal y financiero',
            'accion' => mb_substr($action, 0, 40),
            'tabla_afectada' => $table,
            'registro_clave' => mb_substr($key, 0, 80),
            'antes' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
