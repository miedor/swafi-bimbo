<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventarioActivoRequest;
use App\Http\Requests\StoreMovimientoUbicacionRequest;
use App\Models\Activo;
use App\Models\InventarioActivo;
use App\Models\MovimientoUbicacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UbicacionInventarioController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $resultados = $query
            ->orderBy('pl.nombre')
            ->orderBy('a.numero_activo')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return view('swafi.ubicacion', [
            'resultados' => $resultados,
            'catalogos' => $this->catalogos(),
            'filtros' => $request->all(),
        ]);
    }

    public function storeMovimiento(StoreMovimientoUbicacionRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $activo = Activo::where('numero_activo', $data['numero_activo'])
                ->lockForUpdate()
                ->firstOrFail();

            $antes = $activo->toArray();

            $movimiento = MovimientoUbicacion::create([
                'numero_activo' => $activo->numero_activo,
                'ubicacion_origen_id' => $activo->ubicacion_id,
                'ubicacion_destino_id' => $data['ubicacion_destino_id'],
                'motivo' => $data['motivo'] ?? null,
                'evidencia' => $data['evidencia'] ?? null,
                'fecha_movimiento' => Carbon::parse($data['fecha_movimiento']),
                'responsable_id' => $data['responsable_id'] ?? null,
                'registrado_por' => auth()->id(),
            ]);

            $activo->update([
                'ubicacion_id' => $data['ubicacion_destino_id'],
                'responsable_id' => $data['responsable_id'] ?? $activo->responsable_id,
                'actualizado_por' => auth()->id(),
            ]);

            $despues = $activo->fresh()->toArray();

            $this->registrarBitacora(
                numeroActivo: $activo->numero_activo,
                accion: 'CAMBIO_UBICACION',
                tablaAfectada: 'movimientos_ubicacion',
                registroClave: (string) $movimiento->id,
                antes: [
                    'activo' => $antes,
                ],
                despues: [
                    'activo' => $despues,
                    'movimiento' => $movimiento->toArray(),
                ]
            );
        });

        return redirect()
            ->route('ubicacion', [
                'numero_activo' => $data['numero_activo'],
            ])
            ->with('success', 'La ubicación física del activo se actualizó correctamente.');
    }

    public function storeInventario(StoreInventarioActivoRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $activo = Activo::where('numero_activo', $data['numero_activo'])
                ->lockForUpdate()
                ->firstOrFail();

            $antes = $activo->toArray();

            $inventario = InventarioActivo::create([
                'numero_activo' => $activo->numero_activo,
                'fecha_inventario' => $data['fecha_inventario'],
                'estatus_localizacion' => $data['estatus_localizacion'],
                'ubicacion_verificada_id' => $data['ubicacion_verificada_id'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'verificado_por' => auth()->id(),
            ]);

            $actualizoUbicacion = false;

            if (
                $request->boolean('actualizar_ubicacion')
                && !empty($data['ubicacion_verificada_id'])
            ) {
                $activo->update([
                    'ubicacion_id' => $data['ubicacion_verificada_id'],
                    'actualizado_por' => auth()->id(),
                ]);

                $actualizoUbicacion = true;
            }

            $despues = $activo->fresh()->toArray();

            $this->registrarBitacora(
                numeroActivo: $activo->numero_activo,
                accion: 'REGISTRO_INVENTARIO',
                tablaAfectada: 'inventarios_activo',
                registroClave: (string) $inventario->id,
                antes: [
                    'activo' => $antes,
                ],
                despues: [
                    'activo' => $despues,
                    'inventario' => $inventario->toArray(),
                    'actualizo_ubicacion' => $actualizoUbicacion,
                ]
            );
        });

        return redirect()
            ->route('ubicacion', [
                'numero_activo' => $data['numero_activo'],
            ])
            ->with('success', 'La toma de inventario se registró correctamente.');
    }

    private function baseQuery()
    {
        $latestInventarios = DB::table('inventarios_activo')
            ->select('numero_activo', DB::raw('MAX(id) as inventario_id'))
            ->groupBy('numero_activo');

        $latestMovimientos = DB::table('movimientos_ubicacion')
            ->select('numero_activo', DB::raw('MAX(id) as movimiento_id'))
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoinSub($latestInventarios, 'li', function ($join) {
                $join->on('li.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('inventarios_activo as ia', 'ia.id', '=', 'li.inventario_id')
            ->leftJoin('ubicaciones as uv', 'uv.id', '=', 'ia.ubicacion_verificada_id')
            ->leftJoinSub($latestMovimientos, 'lm', function ($join) {
                $join->on('lm.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('movimientos_ubicacion as mu', 'mu.id', '=', 'lm.movimiento_id')
            ->leftJoin('ubicaciones as ud', 'ud.id', '=', 'mu.ubicacion_destino_id')
            ->select([
                'a.numero_activo',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'a.ubicacion_id',
                'a.responsable_id',

                'pl.id as planta_id',
                'pl.nombre as planta_nombre',

                'ar.id as area_id',
                'ar.nombre as area_nombre',

                'u.codigo_interno as ubicacion_codigo',
                'u.descripcion as ubicacion_descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',

                'r.nombre as responsable_nombre',
                'r.correo as responsable_correo',

                'ia.id as inventario_id',
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
                'ia.observaciones as inventario_observaciones',
                'uv.codigo_interno as ubicacion_verificada_codigo',
                'uv.descripcion as ubicacion_verificada_descripcion',

                'mu.id as movimiento_id',
                'mu.fecha_movimiento',
                'mu.motivo as movimiento_motivo',
                'ud.codigo_interno as ubicacion_destino_codigo',
                'ud.descripcion as ubicacion_destino_descripcion',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('numero_activo')) {
            $query->where('a.numero_activo', 'like', '%' . $request->numero_activo . '%');
        }

        if ($request->filled('planta_id')) {
            $query->where('a.planta_id', $request->planta_id);
        }

        if ($request->filled('area_id')) {
            $query->where('u.area_id', $request->area_id);
        }

        if ($request->filled('ubicacion_id')) {
            $query->where('a.ubicacion_id', $request->ubicacion_id);
        }

        if ($request->filled('responsable_id')) {
            $query->where('a.responsable_id', $request->responsable_id);
        }

        if ($request->filled('estatus_operativo')) {
            $query->where('a.estatus_operativo', $request->estatus_operativo);
        }

        if ($request->filled('estatus_localizacion')) {
            if ($request->estatus_localizacion === 'sin_inventario') {
                $query->whereNull('ia.id');
            } else {
                $query->where('ia.estatus_localizacion', $request->estatus_localizacion);
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('ia.fecha_inventario', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('ia.fecha_inventario', '<=', $request->fecha_hasta);
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

            'areas' => DB::table('areas')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),

            'ubicaciones' => DB::table('ubicaciones as u')
                ->leftJoin('plantas as pl', 'pl.id', '=', 'u.planta_id')
                ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
                ->where('u.estatus', 'activo')
                ->orderBy('pl.nombre')
                ->orderBy('ar.nombre')
                ->orderBy('u.codigo_interno')
                ->select([
                    'u.id',
                    'u.codigo_interno',
                    'u.descripcion',
                    'u.edificio',
                    'u.piso',
                    'u.pasillo',
                    'pl.nombre as planta_nombre',
                    'ar.nombre as area_nombre',
                ])
                ->get(),

            'responsables' => DB::table('responsables')
                ->where('estatus', 'activo')
                ->orderBy('nombre')
                ->get(),
        ];
    }

    private function exportCsv($query)
    {
        $rows = $query
            ->orderBy('pl.nombre')
            ->orderBy('a.numero_activo')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Numero activo',
                'Descripcion',
                'Planta',
                'Area',
                'Ubicacion actual',
                'Responsable',
                'Estatus operativo',
                'Fecha inventario',
                'Estatus localizacion',
                'Ultimo movimiento',
                'Motivo movimiento',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->numero_activo,
                    $row->activo_descripcion,
                    $row->planta_nombre,
                    $row->area_nombre,
                    $this->formatUbicacion($row),
                    $row->responsable_nombre,
                    $row->estatus_operativo,
                    $row->fecha_inventario,
                    $row->estatus_localizacion,
                    $row->fecha_movimiento,
                    $row->movimiento_motivo,
                ]);
            }

            fclose($output);
        }, 'ubicacion_inventario_swafi_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatUbicacion($row): string
    {
        $parts = array_filter([
            $row->ubicacion_codigo,
            $row->ubicacion_descripcion,
            $row->edificio,
            $row->piso,
            $row->pasillo,
        ]);

        return $parts ? implode(' / ', $parts) : 'Sin ubicación';
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
            'modulo' => 'M02 Control fiscal, financiero y ubicación física',
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
