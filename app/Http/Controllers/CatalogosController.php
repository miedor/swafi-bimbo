<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
