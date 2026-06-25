<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreValorActivoRequest;
use App\Models\ValorActivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $valorEnLibros = $data['valor_en_libros'];

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
            'vida_util_meses' => $data['vida_util_meses'],
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
