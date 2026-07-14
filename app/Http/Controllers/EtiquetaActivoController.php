<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EtiquetaActivoController extends Controller
{
    public function show(Request $request, Activo $activo): View
    {
        $datos = $this->assetData($activo->numero_activo);

        abort_unless($datos, 404, 'El activo solicitado no existe en SWAFI.');

        $qrUrl = route('busqueda', [
            'numero_activo' => $activo->numero_activo,
        ]);

        $this->registrarBitacora(
            request: $request,
            numeroActivo: $activo->numero_activo,
            accion: 'ETIQUETA_QR_GENERADA',
            detalles: [
                'url_qr' => $qrUrl,
                'expediente_id' => $datos->expediente_id,
            ]
        );

        return view('swafi.etiqueta-activo', [
            'activo' => $datos,
            'qrUrl' => $qrUrl,
            'nombreArchivo' => 'etiqueta_swafi_' . $this->safeFileName($activo->numero_activo) . '.png',
            'nombreArchivoPdf' => 'etiqueta_swafi_' . $this->safeFileName($activo->numero_activo) . '.pdf',
        ]);
    }

    public function audit(Request $request, Activo $activo): JsonResponse
    {
        $validated = $request->validate([
            'evento' => [
                'required',
                'string',
                Rule::in(['descargar_png', 'descargar_pdf', 'imprimir']),
            ],
        ], [
            'evento.required' => 'El evento de etiqueta es obligatorio.',
            'evento.in' => 'El evento de etiqueta no es válido.',
        ]);

        $accion = match ($validated['evento']) {
            'imprimir' => 'ETIQUETA_QR_IMPRESA',
            'descargar_pdf' => 'ETIQUETA_QR_DESCARGADA_PDF',
            default => 'ETIQUETA_QR_DESCARGADA_PNG',
        };

        $this->registrarBitacora(
            request: $request,
            numeroActivo: $activo->numero_activo,
            accion: $accion,
            detalles: [
                'evento' => $validated['evento'],
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Evento de etiqueta registrado en la bitácora.',
        ]);
    }

    private function assetData(string $numeroActivo): ?object
    {
        $latestExpediente = DB::table('expedientes')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        $latestInventario = DB::table('inventarios_activo')
            ->select('numero_activo', DB::raw('MAX(id) as inventario_id'))
            ->groupBy('numero_activo');

        return DB::table('activos as a')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('ubicaciones as u', 'u.id', '=', 'a.ubicacion_id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'u.area_id')
            ->leftJoin('responsables as r', 'r.id', '=', 'a.responsable_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoinSub($latestExpediente, 'le', function ($join): void {
                $join->on('le.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoinSub($latestInventario, 'li', function ($join): void {
                $join->on('li.numero_activo', '=', 'a.numero_activo');
            })
            ->leftJoin('inventarios_activo as ia', 'ia.id', '=', 'li.inventario_id')
            ->where('a.numero_activo', $numeroActivo)
            ->select([
                'a.numero_activo',
                'a.descripcion',
                'a.serie',
                'a.marca',
                'a.modelo',
                'a.estatus_operativo',
                'a.estatus_documental',
                'pl.nombre as planta_nombre',
                'ar.nombre as area_nombre',
                'u.codigo_interno as ubicacion_codigo',
                'u.descripcion as ubicacion_descripcion',
                'u.edificio',
                'u.piso',
                'u.pasillo',
                'r.nombre as responsable_nombre',
                'ta.descripcion as tipo_activo',
                'e.id as expediente_id',
                'e.folio_factura',
                'ia.fecha_inventario',
                'ia.estatus_localizacion',
            ])
            ->first();
    }

    private function registrarBitacora(
        Request $request,
        string $numeroActivo,
        string $accion,
        array $detalles
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => $this->userId(),
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => $accion,
                'tabla_afectada' => 'activos',
                'registro_clave' => $numeroActivo,
                'antes' => null,
                'despues' => json_encode($detalles, JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La generación de la etiqueta no debe fallar por un error de bitácora.
        }
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }

    private function safeFileName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value));

        return trim((string) $safe, '_') ?: 'activo';
    }
}
