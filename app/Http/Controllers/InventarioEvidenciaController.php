<?php

namespace App\Http\Controllers;

use App\Models\InventarioEvidencia;
use App\Services\SwafiStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioEvidenciaController extends Controller
{
    public function __construct(private readonly SwafiStorageService $storage)
    {
    }

    public function show(InventarioEvidencia $evidencia): StreamedResponse|RedirectResponse
    {
        $context = $this->resolveContext($evidencia);
        $validation = $this->validateStoredFile($evidencia);

        if (!$validation['ok']) {
            return $this->redirectWithError($context->expediente_id, $validation['message']);
        }

        $this->registrarBitacora(
            numeroActivo: $evidencia->numero_activo,
            accion: 'EVIDENCIA_INVENTARIO_VISUALIZADA',
            evidencia: $evidencia
        );

        return $this->storage->inlineResponse(
            disk: $validation['disk'],
            path: $validation['path'],
            downloadName: $evidencia->nombre_archivo,
            mimeType: $evidencia->mime_type ?: $validation['mime_type']
        );
    }

    public function download(InventarioEvidencia $evidencia): StreamedResponse|RedirectResponse
    {
        $context = $this->resolveContext($evidencia);
        $validation = $this->validateStoredFile($evidencia);

        if (!$validation['ok']) {
            return $this->redirectWithError($context->expediente_id, $validation['message']);
        }

        $this->registrarBitacora(
            numeroActivo: $evidencia->numero_activo,
            accion: 'EVIDENCIA_INVENTARIO_DESCARGADA',
            evidencia: $evidencia
        );

        return $this->storage->downloadResponse(
            disk: $validation['disk'],
            path: $validation['path'],
            downloadName: $evidencia->nombre_archivo,
            mimeType: $evidencia->mime_type ?: $validation['mime_type']
        );
    }

    public function destroy(InventarioEvidencia $evidencia): RedirectResponse
    {
        $context = $this->resolveContext($evidencia);

        if (!$evidencia->vigente) {
            return $this->redirectWithError(
                $context->expediente_id,
                'La evidencia seleccionada ya se encuentra dada de baja.'
            );
        }

        DB::transaction(function () use ($evidencia): void {
            $antes = $evidencia->toArray();

            $evidencia->update([
                'vigente' => false,
            ]);

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $evidencia->numero_activo,
                'user_id' => $this->userId(),
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => 'EVIDENCIA_INVENTARIO_BAJA_LOGICA',
                'tabla_afectada' => 'inventario_evidencias',
                'registro_clave' => (string) $evidencia->id,
                'antes' => json_encode($antes, JSON_UNESCAPED_UNICODE),
                'despues' => json_encode($evidencia->fresh()->toArray(), JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('expediente', [
                'expediente' => $context->expediente_id,
                'tab' => 'ubicacion',
            ])
            ->with('success', 'La evidencia se dio de baja. El archivo físico se conserva para trazabilidad.');
    }

    private function resolveContext(InventarioEvidencia $evidencia): object
    {
        abort_if(!$evidencia->vigente, 404, 'La evidencia solicitada no se encuentra vigente.');

        $context = DB::table('inventario_evidencias as ie')
            ->join('inventarios_activo as ia', 'ia.id', '=', 'ie.inventario_id')
            ->where('ie.id', $evidencia->id)
            ->select([
                'ia.id as inventario_id',
                'ia.numero_activo',
            ])
            ->first();

        abort_if(!$context, 404, 'No se encontró el inventario relacionado con la evidencia.');

        $context->expediente_id = DB::table('expedientes')
            ->where('numero_activo', $context->numero_activo)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('id');

        return $context;
    }

    private function validateStoredFile(InventarioEvidencia $evidencia): array
    {
        $path = trim((string) $evidencia->ruta_archivo);

        if ($path === '') {
            return [
                'ok' => false,
                'disk' => $evidencia->storage_disk ?: 'local',
                'path' => '',
                'message' => 'La evidencia no tiene una ruta de almacenamiento registrada.',
            ];
        }

        return $this->storage->validate(
            $evidencia->storage_disk,
            $path,
            $evidencia->hash_sha256
        );
    }

    private function redirectWithError(?int $expedienteId, string $message): RedirectResponse
    {
        if ($expedienteId) {
            return redirect()
                ->route('expediente', [
                    'expediente' => $expedienteId,
                    'tab' => 'ubicacion',
                ])
                ->withErrors(['evidencias' => $message]);
        }

        return redirect()
            ->route('ubicacion')
            ->withErrors(['evidencias' => $message]);
    }

    private function registrarBitacora(
        string $numeroActivo,
        string $accion,
        InventarioEvidencia $evidencia
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => $this->userId(),
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => $accion,
                'tabla_afectada' => 'inventario_evidencias',
                'registro_clave' => (string) $evidencia->id,
                'antes' => null,
                'despues' => json_encode([
                    'inventario_id' => $evidencia->inventario_id,
                    'nombre_archivo' => $evidencia->nombre_archivo,
                    'storage_disk' => $evidencia->storage_disk ?: 'local',
                    'hash_sha256' => $evidencia->hash_sha256,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // La entrega del archivo no debe fallar por una incidencia secundaria de bitácora.
        }
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}
