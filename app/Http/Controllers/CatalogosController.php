<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogosController extends Controller
{
    public function index(Request $request)
    {
        $catalogoActivo = $this->normalizeCatalog($request->input('catalogo', 'proveedores'));
        $query = $this->baseQuery($catalogoActivo);

        $this->applyFilters($query, $request, $catalogoActivo);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query, $catalogoActivo);
        }

        $this->applyOrder($query, $catalogoActivo);

        $resultados = $query
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('swafi.catalogos', [
            'catalogoActivo' => $catalogoActivo,
            'catalogosDisponibles' => $this->catalogs(),
            'columnas' => $this->columnsFor($catalogoActivo),
            'resultados' => $resultados,
            'registroEdit' => $this->findForEdit($catalogoActivo, $request->input('editar')),
            'filtros' => $request->all(),
            'opciones' => $this->options(),
            'kpis' => $this->buildKpis($catalogoActivo),
            'headersLayout' => $this->headersFor($catalogoActivo),
        ]);
    }

    public function store(Request $request)
    {
        $catalogo = $this->normalizeCatalog($request->input('catalogo'));
        $id = $request->input('id');

        $validated = $this->validateCatalog($request, $catalogo, $id);

        $before = null;

        if ($id) {
            $before = DB::table($this->tableFor($catalogo))
                ->where('id', $id)
                ->first();
        }

        DB::transaction(function () use ($catalogo, $validated, $id, $before) {
            $table = $this->tableFor($catalogo);
            $now = now();

            if ($id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update(array_merge($validated, [
                        'updated_at' => $now,
                    ]));

                $registroId = (string) $id;
                $accion = 'ACTUALIZACION_CATALOGO';
            } else {
                $registroId = (string) DB::table($table)->insertGetId(array_merge($validated, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                $accion = 'ALTA_CATALOGO';
            }

            $after = DB::table($table)
                ->where('id', $registroId)
                ->first();

            $this->registrarBitacora(
                accion: $accion,
                tablaAfectada: $table,
                registroClave: $registroId,
                antes: $before ? (array) $before : null,
                despues: $after ? (array) $after : null
            );
        });

        return redirect()
            ->route('catalogos', ['catalogo' => $catalogo])
            ->with('success', $id ? 'El catálogo se actualizó correctamente.' : 'El registro se creó correctamente.');
    }

    public function importar(Request $request)
    {
        $catalogo = $this->normalizeCatalog($request->input('catalogo'));

        $request->validate([
            'catalogo' => ['required', Rule::in(array_keys($this->catalogs()))],
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'archivo_csv.required' => 'Debes seleccionar un archivo CSV para importar.',
            'archivo_csv.file' => 'El archivo seleccionado no es válido.',
            'archivo_csv.mimes' => 'El archivo debe tener extensión CSV o TXT.',
            'archivo_csv.max' => 'El archivo CSV no debe superar los 10 MB.',
        ]);

        $file = $request->file('archivo_csv');
        $rows = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!$rows || count($rows) < 2) {
            return back()->withErrors([
                'archivo_csv' => 'El archivo no contiene registros para importar.',
            ]);
        }

        $delimiter = $this->detectDelimiter($rows[0]);
        $headers = str_getcsv($rows[0], $delimiter);
        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader($header), $headers);

        $requiredHeaders = $this->requiredHeadersFor($catalogo);
        $missingHeaders = array_diff($requiredHeaders, $normalizedHeaders);

        if (!empty($missingHeaders)) {
            return back()->withErrors([
                'archivo_csv' => 'El layout no contiene los encabezados requeridos: ' . implode(', ', $missingHeaders),
            ]);
        }

        $headerIndexes = array_flip($normalizedHeaders);
        $headersToRead = $this->headersFor($catalogo);

        $summary = [
            'catalogo' => $this->catalogs()[$catalogo] ?? $catalogo,
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

                $prepared = $this->prepareImportRow($catalogo, $data, $lineNumber, $summary);

                if ($prepared === null) {
                    continue;
                }

                $table = $this->tableFor($catalogo);
                $existing = $this->findExistingImportRecord($catalogo, $prepared);
                $before = $existing ? (array) $existing : null;
                $now = now();

                if ($existing) {
                    DB::table($table)
                        ->where('id', $existing->id)
                        ->update(array_merge($prepared, [
                            'updated_at' => $now,
                        ]));

                    $registroId = (string) $existing->id;
                    $summary['actualizados']++;
                    $accion = 'IMPORTACION_CATALOGO_ACTUALIZACION';
                } else {
                    $registroId = (string) DB::table($table)->insertGetId(array_merge($prepared, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));

                    $summary['insertados']++;
                    $accion = 'IMPORTACION_CATALOGO_ALTA';
                }

                $after = DB::table($table)
                    ->where('id', $registroId)
                    ->first();

                $this->registrarBitacora(
                    accion: $accion,
                    tablaAfectada: $table,
                    registroClave: $registroId,
                    antes: $before,
                    despues: $after ? (array) $after : null
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
            ->route('catalogos', ['catalogo' => $catalogo])
            ->with('success', 'La carga masiva del catálogo fue procesada correctamente.')
            ->with('import_summary', $summary);
    }

    public function plantillaCsv(Request $request)
    {
        $catalogo = $this->normalizeCatalog($request->input('catalogo', 'proveedores'));
        $headers = $this->headersFor($catalogo);
        $example = $this->exampleRowFor($catalogo);

        return response()->streamDownload(function () use ($headers, $example) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn ($header) => Str::headline(str_replace('_', ' ', $header)), $headers));
            fputcsv($output, $example);

            fclose($output);
        }, 'plantilla_catalogo_swafi_' . $catalogo . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(string $catalogo, int $id)
    {
        $catalogo = $this->normalizeCatalog($catalogo);
        $table = $this->tableFor($catalogo);

        $before = DB::table($table)
            ->where('id', $id)
            ->first();

        if (!$before) {
            return redirect()
                ->route('catalogos', ['catalogo' => $catalogo])
                ->withErrors(['catalogo' => 'El registro seleccionado no existe.']);
        }

        DB::transaction(function () use ($table, $id, $before) {
            DB::table($table)
                ->where('id', $id)
                ->update([
                    'estatus' => 'inactivo',
                    'updated_at' => now(),
                ]);

            $after = DB::table($table)
                ->where('id', $id)
                ->first();

            $this->registrarBitacora(
                accion: 'DESACTIVACION_CATALOGO',
                tablaAfectada: $table,
                registroClave: (string) $id,
                antes: (array) $before,
                despues: $after ? (array) $after : null
            );
        });

        return redirect()
            ->route('catalogos', ['catalogo' => $catalogo])
            ->with('success', 'El registro fue desactivado correctamente. Se conserva por trazabilidad.');
    }

    private function catalogs(): array
    {
        return [
            'proveedores' => 'Proveedores',
            'plantas' => 'Plantas',
            'centros_costo' => 'Centros de costo',
            'tipos_activo' => 'Tipos de activo',
            'areas' => 'Áreas',
            'ubicaciones' => 'Ubicaciones',
            'responsables' => 'Responsables',
        ];
    }

    private function normalizeCatalog(?string $catalogo): string
    {
        $catalogo = (string) $catalogo;

        return array_key_exists($catalogo, $this->catalogs())
            ? $catalogo
            : 'proveedores';
    }

    private function tableFor(string $catalogo): string
    {
        return match ($catalogo) {
            'proveedores' => 'proveedores',
            'plantas' => 'plantas',
            'centros_costo' => 'centros_costo',
            'tipos_activo' => 'tipos_activo',
            'areas' => 'areas',
            'ubicaciones' => 'ubicaciones',
            'responsables' => 'responsables',
            default => 'proveedores',
        };
    }

    private function baseQuery(string $catalogo)
    {
        return match ($catalogo) {
            'areas' => DB::table('areas as a')
                ->leftJoin('plantas as p', 'p.id', '=', 'a.planta_id')
                ->select([
                    'a.id',
                    'a.planta_id',
                    'p.clave as planta_clave',
                    'p.nombre as planta_nombre',
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
            $buscar = '%' . trim($request->input('buscar')) . '%';

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
                    $where->where('clave', 'like', $buscar)
                        ->orWhere('descripcion', 'like', $buscar);
                }),

                'tipos_activo' => $query->where(function ($where) use ($buscar) {
                    $where->where('clave', 'like', $buscar)
                        ->orWhere('descripcion', 'like', $buscar);
                }),

                'areas' => $query->where(function ($where) use ($buscar) {
                    $where->where('a.nombre', 'like', $buscar)
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

            if ($catalogo === 'areas') {
                $query->where('a.estatus', $estatus);
            } elseif ($catalogo === 'ubicaciones') {
                $query->where('u.estatus', $estatus);
            } else {
                $query->where('estatus', $estatus);
            }
        }

        if ($request->filled('planta_id') && in_array($catalogo, ['areas', 'ubicaciones'], true)) {
            if ($catalogo === 'areas') {
                $query->where('a.planta_id', $request->input('planta_id'));
            } else {
                $query->where('u.planta_id', $request->input('planta_id'));
            }
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
            'centros_costo' => $query->orderBy('clave'),
            'tipos_activo' => $query->orderBy('descripcion'),
            'areas' => $query->orderBy('p.nombre')->orderBy('a.nombre'),
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
                'estado' => 'Estado',
                'pais' => 'País',
                'estatus' => 'Estatus',
            ],

            'centros_costo' => [
                'clave' => 'Clave',
                'descripcion' => 'Descripción',
                'estatus' => 'Estatus',
            ],

            'tipos_activo' => [
                'clave' => 'Clave',
                'descripcion' => 'Descripción',
                'vida_util_meses' => 'Vida útil meses',
                'estatus' => 'Estatus',
            ],

            'areas' => [
                'planta_nombre' => 'Planta',
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

    private function validateCatalog(Request $request, string $catalogo, ?string $id): array
    {
        $estatusRule = ['required', Rule::in(['activo', 'inactivo'])];

        return match ($catalogo) {
            'proveedores' => $request->validate([
                'rfc' => [
                    'required',
                    'string',
                    'max:13',
                    Rule::unique('proveedores', 'rfc')->ignore($id),
                ],
                'nombre' => ['required', 'string', 'max:150'],
                'correo' => ['nullable', 'email', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'plantas' => $request->validate([
                'clave' => [
                    'required',
                    'string',
                    'max:30',
                    Rule::unique('plantas', 'clave')->ignore($id),
                ],
                'nombre' => ['required', 'string', 'max:150'],
                'estado' => ['nullable', 'string', 'max:100'],
                'pais' => ['required', 'string', 'max:80'],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'centros_costo' => $request->validate([
                'clave' => [
                    'required',
                    'string',
                    'max:30',
                    Rule::unique('centros_costo', 'clave')->ignore($id),
                ],
                'descripcion' => ['required', 'string', 'max:150'],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'tipos_activo' => $request->validate([
                'clave' => [
                    'required',
                    'string',
                    'max:30',
                    Rule::unique('tipos_activo', 'clave')->ignore($id),
                ],
                'descripcion' => ['required', 'string', 'max:120'],
                'vida_util_meses' => ['nullable', 'integer', 'min:1', 'max:600'],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'areas' => $request->validate([
                'planta_id' => ['required', 'integer', 'exists:plantas,id'],
                'nombre' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('areas', 'nombre')
                        ->where(fn ($query) => $query->where('planta_id', $request->input('planta_id')))
                        ->ignore($id),
                ],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'ubicaciones' => $request->validate([
                'planta_id' => ['required', 'integer', 'exists:plantas,id'],
                'area_id' => ['nullable', 'integer', 'exists:areas,id'],
                'edificio' => ['nullable', 'string', 'max:100'],
                'piso' => ['nullable', 'string', 'max:50'],
                'pasillo' => ['nullable', 'string', 'max:50'],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'codigo_interno' => [
                    'nullable',
                    'string',
                    'max:60',
                    Rule::unique('ubicaciones', 'codigo_interno')->ignore($id),
                ],
                'estatus' => $estatusRule,
            ], $this->messages()),

            'responsables' => $request->validate([
                'nombre' => ['required', 'string', 'max:120'],
                'correo' => ['nullable', 'email', 'max:120'],
                'telefono' => ['nullable', 'string', 'max:30'],
                'estatus' => $estatusRule,
            ], $this->messages()),

            default => [],
        };
    }

    private function headersFor(string $catalogo): array
    {
        return match ($catalogo) {
            'proveedores' => ['rfc', 'nombre', 'correo', 'telefono', 'estatus'],
            'plantas' => ['clave', 'nombre', 'estado', 'pais', 'estatus'],
            'centros_costo' => ['clave', 'descripcion', 'estatus'],
            'tipos_activo' => ['clave', 'descripcion', 'vida_util_meses', 'estatus'],
            'areas' => ['planta_clave', 'nombre', 'estatus'],
            'ubicaciones' => ['planta_clave', 'area_nombre', 'codigo_interno', 'edificio', 'piso', 'pasillo', 'descripcion', 'estatus'],
            'responsables' => ['nombre', 'correo', 'telefono', 'estatus'],
            default => [],
        };
    }

    private function requiredHeadersFor(string $catalogo): array
    {
        return match ($catalogo) {
            'proveedores' => ['rfc', 'nombre'],
            'plantas' => ['clave', 'nombre'],
            'centros_costo' => ['clave', 'descripcion'],
            'tipos_activo' => ['clave', 'descripcion'],
            'areas' => ['planta_clave', 'nombre'],
            'ubicaciones' => ['planta_clave', 'codigo_interno'],
            'responsables' => ['nombre'],
            default => [],
        };
    }

    private function exampleRowFor(string $catalogo): array
    {
        return match ($catalogo) {
            'proveedores' => ['ACM010101ABC', 'Proveedor industrial del centro', 'contacto@proveedor.com', '5555555555', 'activo'],
            'plantas' => ['PLT-SM', 'Planta Santa María', 'Ciudad de México', 'México', 'activo'],
            'centros_costo' => ['CC-PLA-200', 'Producción línea 2', 'activo'],
            'tipos_activo' => ['EQP', 'Equipo de producción', '120', 'activo'],
            'areas' => ['PLT-SM', 'Producción', 'activo'],
            'ubicaciones' => ['PLT-SM', 'Producción', 'UBI-SM-PRO-L3-PB', 'Edificio B', 'PB', 'Línea 3', 'Producción línea 3 planta baja', 'activo'],
            'responsables' => ['Jorge Méndez', 'jorge.mendez@bimbo.local', '5555555555', 'activo'],
            default => [],
        };
    }

    private function prepareImportRow(string $catalogo, array $data, int $lineNumber, array &$summary): ?array
    {
        $estatus = $this->normalizeEstatus($data['estatus'] ?? 'activo');

        if ($estatus === null) {
            $this->rejectRow($summary, $lineNumber, 'el estatus debe ser activo o inactivo.');
            return null;
        }

        return match ($catalogo) {
            'proveedores' => $this->prepareProveedor($data, $lineNumber, $summary, $estatus),
            'plantas' => $this->preparePlanta($data, $lineNumber, $summary, $estatus),
            'centros_costo' => $this->prepareCentroCosto($data, $lineNumber, $summary, $estatus),
            'tipos_activo' => $this->prepareTipoActivo($data, $lineNumber, $summary, $estatus),
            'areas' => $this->prepareArea($data, $lineNumber, $summary, $estatus),
            'ubicaciones' => $this->prepareUbicacion($data, $lineNumber, $summary, $estatus),
            'responsables' => $this->prepareResponsable($data, $lineNumber, $summary, $estatus),
            default => null,
        };
    }

    private function prepareProveedor(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $rfc = strtoupper($this->normalizeCell($data['rfc'] ?? ''));
        $nombre = $this->normalizeCell($data['nombre'] ?? '');

        if ($rfc === '' || mb_strlen($rfc) > 13) {
            $this->rejectRow($summary, $lineNumber, 'el RFC es obligatorio y no debe superar 13 caracteres.');
            return null;
        }

        if ($nombre === '') {
            $this->rejectRow($summary, $lineNumber, 'el nombre del proveedor es obligatorio.');
            return null;
        }

        return [
            'rfc' => $rfc,
            'nombre' => $nombre,
            'correo' => $this->nullableString($data['correo'] ?? null, 120),
            'telefono' => $this->nullableString($data['telefono'] ?? null, 30),
            'estatus' => $estatus,
        ];
    }

    private function preparePlanta(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $clave = strtoupper($this->normalizeCell($data['clave'] ?? ''));
        $nombre = $this->normalizeCell($data['nombre'] ?? '');

        if ($clave === '' || mb_strlen($clave) > 30) {
            $this->rejectRow($summary, $lineNumber, 'la clave de planta es obligatoria y no debe superar 30 caracteres.');
            return null;
        }

        if ($nombre === '') {
            $this->rejectRow($summary, $lineNumber, 'el nombre de planta es obligatorio.');
            return null;
        }

        return [
            'clave' => $clave,
            'nombre' => $nombre,
            'estado' => $this->nullableString($data['estado'] ?? null, 100),
            'pais' => $this->normalizeCell($data['pais'] ?? '') ?: 'México',
            'estatus' => $estatus,
        ];
    }

    private function prepareCentroCosto(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $clave = strtoupper($this->normalizeCell($data['clave'] ?? ''));
        $descripcion = $this->normalizeCell($data['descripcion'] ?? '');

        if ($clave === '' || mb_strlen($clave) > 30) {
            $this->rejectRow($summary, $lineNumber, 'la clave de centro de costo es obligatoria y no debe superar 30 caracteres.');
            return null;
        }

        if ($descripcion === '') {
            $this->rejectRow($summary, $lineNumber, 'la descripción del centro de costo es obligatoria.');
            return null;
        }

        return [
            'clave' => $clave,
            'descripcion' => $descripcion,
            'estatus' => $estatus,
        ];
    }

    private function prepareTipoActivo(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $clave = strtoupper($this->normalizeCell($data['clave'] ?? ''));
        $descripcion = $this->normalizeCell($data['descripcion'] ?? '');
        $vidaUtil = $this->normalizeCell($data['vida_util_meses'] ?? '');

        if ($clave === '' || mb_strlen($clave) > 30) {
            $this->rejectRow($summary, $lineNumber, 'la clave de tipo de activo es obligatoria y no debe superar 30 caracteres.');
            return null;
        }

        if ($descripcion === '') {
            $this->rejectRow($summary, $lineNumber, 'la descripción del tipo de activo es obligatoria.');
            return null;
        }

        if ($vidaUtil !== '' && (!ctype_digit($vidaUtil) || (int) $vidaUtil < 1 || (int) $vidaUtil > 600)) {
            $this->rejectRow($summary, $lineNumber, 'la vida útil debe ser un número entre 1 y 600 meses.');
            return null;
        }

        return [
            'clave' => $clave,
            'descripcion' => $descripcion,
            'vida_util_meses' => $vidaUtil !== '' ? (int) $vidaUtil : null,
            'estatus' => $estatus,
        ];
    }

    private function prepareArea(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $plantaClave = strtoupper($this->normalizeCell($data['planta_clave'] ?? ''));
        $nombre = $this->normalizeCell($data['nombre'] ?? '');

        if ($plantaClave === '') {
            $this->rejectRow($summary, $lineNumber, 'la planta_clave es obligatoria.');
            return null;
        }

        $plantaId = DB::table('plantas')->where('clave', $plantaClave)->value('id');

        if (!$plantaId) {
            $this->rejectRow($summary, $lineNumber, "la planta {$plantaClave} no existe. Primero importa o registra la planta.");
            return null;
        }

        if ($nombre === '') {
            $this->rejectRow($summary, $lineNumber, 'el nombre del área es obligatorio.');
            return null;
        }

        return [
            'planta_id' => $plantaId,
            'nombre' => $nombre,
            'estatus' => $estatus,
        ];
    }

    private function prepareUbicacion(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $plantaClave = strtoupper($this->normalizeCell($data['planta_clave'] ?? ''));
        $areaNombre = $this->normalizeCell($data['area_nombre'] ?? '');
        $codigoInterno = strtoupper($this->normalizeCell($data['codigo_interno'] ?? ''));

        if ($plantaClave === '') {
            $this->rejectRow($summary, $lineNumber, 'la planta_clave es obligatoria.');
            return null;
        }

        $plantaId = DB::table('plantas')->where('clave', $plantaClave)->value('id');

        if (!$plantaId) {
            $this->rejectRow($summary, $lineNumber, "la planta {$plantaClave} no existe. Primero importa o registra la planta.");
            return null;
        }

        if ($codigoInterno === '') {
            $this->rejectRow($summary, $lineNumber, 'el codigo_interno de ubicación es obligatorio para evitar duplicados.');
            return null;
        }

        $areaId = null;

        if ($areaNombre !== '') {
            $areaId = DB::table('areas')
                ->where('planta_id', $plantaId)
                ->where('nombre', $areaNombre)
                ->value('id');

            if (!$areaId) {
                $this->rejectRow($summary, $lineNumber, "el área {$areaNombre} no existe para la planta {$plantaClave}. Primero importa o registra el área.");
                return null;
            }
        }

        return [
            'planta_id' => $plantaId,
            'area_id' => $areaId,
            'codigo_interno' => $codigoInterno,
            'edificio' => $this->nullableString($data['edificio'] ?? null, 100),
            'piso' => $this->nullableString($data['piso'] ?? null, 50),
            'pasillo' => $this->nullableString($data['pasillo'] ?? null, 50),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null, 255),
            'estatus' => $estatus,
        ];
    }

    private function prepareResponsable(array $data, int $lineNumber, array &$summary, string $estatus): ?array
    {
        $nombre = $this->normalizeCell($data['nombre'] ?? '');

        if ($nombre === '') {
            $this->rejectRow($summary, $lineNumber, 'el nombre del responsable es obligatorio.');
            return null;
        }

        return [
            'nombre' => $nombre,
            'correo' => $this->nullableString($data['correo'] ?? null, 120),
            'telefono' => $this->nullableString($data['telefono'] ?? null, 30),
            'estatus' => $estatus,
        ];
    }

    private function findExistingImportRecord(string $catalogo, array $prepared)
    {
        return match ($catalogo) {
            'proveedores' => DB::table('proveedores')->where('rfc', $prepared['rfc'])->first(),
            'plantas' => DB::table('plantas')->where('clave', $prepared['clave'])->first(),
            'centros_costo' => DB::table('centros_costo')->where('clave', $prepared['clave'])->first(),
            'tipos_activo' => DB::table('tipos_activo')->where('clave', $prepared['clave'])->first(),
            'areas' => DB::table('areas')
                ->where('planta_id', $prepared['planta_id'])
                ->where('nombre', $prepared['nombre'])
                ->first(),
            'ubicaciones' => DB::table('ubicaciones')->where('codigo_interno', $prepared['codigo_interno'])->first(),
            'responsables' => $this->findExistingResponsable($prepared),
            default => null,
        };
    }

    private function findExistingResponsable(array $prepared)
    {
        if (!empty($prepared['correo'])) {
            $responsable = DB::table('responsables')
                ->where('correo', $prepared['correo'])
                ->first();

            if ($responsable) {
                return $responsable;
            }
        }

        return DB::table('responsables')
            ->where('nombre', $prepared['nombre'])
            ->first();
    }

    private function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'unique' => 'El valor capturado en :attribute ya existe.',
            'email' => 'El correo electrónico no tiene un formato válido.',
            'exists' => 'El valor seleccionado en :attribute no existe.',
            'integer' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute no cumple el valor mínimo permitido.',
            'max' => 'El campo :attribute supera la longitud permitida.',
            'in' => 'El campo :attribute contiene un valor no válido.',
        ];
    }

    private function options(): array
    {
        return [
            'plantas' => DB::table('plantas')
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
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows, $columns) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns));

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($columns) as $key) {
                    $line[] = data_get($row, $key);
                }

                fputcsv($output, $line);
            }

            fclose($output);
        }, 'catalogo_swafi_' . $catalogo . '_' . now()->format('Ymd_His') . '.csv', [
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

    private function normalizeEstatus(?string $value): ?string
    {
        $value = $this->normalizeHeader($value ?: 'activo');

        return match ($value) {
            'activo', 'activa', '1', 'si' => 'activo',
            'inactivo', 'inactiva', '0', 'no' => 'inactivo',
            default => null,
        };
    }

    private function nullableString(?string $value, int $maxLength): ?string
    {
        $value = $this->normalizeCell($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function rejectRow(array &$summary, int $lineNumber, string $reason): void
    {
        $summary['rechazados']++;
        $summary['errores'][] = "Fila {$lineNumber}: {$reason}";
    }

    private function registrarBitacora(
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        ?array $antes,
        ?array $despues
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => null,
            'user_id' => auth()->id(),
            'modulo' => 'M04 Administración y seguridad del sistema',
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
