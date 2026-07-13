<?php

namespace App\Http\Controllers;

use App\Models\InventarioEvidencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventarioEvidenciaController extends Controller
{
    public function show(InventarioEvidencia $evidencia): BinaryFileResponse|RedirectResponse
    {
        $context = $this->resolveContext($evidencia);
        $validation = $this->validatePhysicalFile($evidencia);

        if (!$validation['ok']) {
            return $this->redirectWithError($context->expediente_id, $validation['message']);
        }

        $this->registrarBitacora(
            numeroActivo: $evidencia->numero_activo,
            accion: 'EVIDENCIA_INVENTARIO_VISUALIZADA',
            evidencia: $evidencia
        );

        return response()->file($validation['path'], [
            'Content-Type' => $evidencia->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($evidencia->nombre_archivo) . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    }

    public function download(InventarioEvidencia $evidencia): BinaryFileResponse|RedirectResponse
    {
        $context = $this->resolveContext($evidencia);
        $validation = $this->validatePhysicalFile($evidencia);

        if (!$validation['ok']) {
            return $this->redirectWithError($context->expediente_id, $validation['message']);
        }

        $this->registrarBitacora(
            numeroActivo: $evidencia->numero_activo,
            accion: 'EVIDENCIA_INVENTARIO_DESCARGADA',
            evidencia: $evidencia
        );

        return response()->download(
            $validation['path'],
            $evidencia->nombre_archivo,
            ['Content-Type' => $evidencia->mime_type ?: 'application/octet-stream']
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

        DB::transaction(function () use ($evidencia) {
            $antes = $evidencia->toArray();

            $evidencia->update([
                'vigente' => false,
            ]);

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $evidencia->numero_activo,
                'user_id' => $this->userId(),
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'accion' => 'EVIDENCIA_INVENTARIO_ELIMINADA',
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
            ->orderByDesc('id')
            ->value('id');

        return $context;
    }

    private function validatePhysicalFile(InventarioEvidencia $evidencia): array
    {
        $path = trim((string) $evidencia->ruta_archivo);

        if ($path === '' || !Storage::disk('local')->exists($path)) {
            return [
                'ok' => false,
                'path' => null,
                'message' => 'La evidencia está registrada, pero el archivo físico no se localizó en el almacenamiento privado.',
            ];
        }

        $absolutePath = Storage::disk('local')->path($path);

        if (!is_file($absolutePath)) {
            return [
                'ok' => false,
                'path' => null,
                'message' => 'La ruta de la evidencia no corresponde a un archivo físico válido.',
            ];
        }

        if ($evidencia->hash_sha256) {
            $currentHash = hash_file('sha256', $absolutePath);

            if (!hash_equals((string) $evidencia->hash_sha256, (string) $currentHash)) {
                return [
                    'ok' => false,
                    'path' => null,
                    'message' => 'La evidencia no superó la validación de integridad SHA-256. No se permite abrir ni descargar el archivo.',
                ];
            }
        }

        return [
            'ok' => true,
            'path' => $absolutePath,
            'message' => null,
        ];
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
                    'hash_sha256' => $evidencia->hash_sha256,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La entrega del archivo no debe fallar por una incidencia secundaria de bitácora.
        }
    }

    private function userId(): ?int
    {
        $userId = (int) (session('swafi_user_id') ?: auth()->id());

        return $userId > 0 ? $userId : null;
    }
}
