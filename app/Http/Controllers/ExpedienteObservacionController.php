<?php

namespace App\Http\Controllers;

use App\Models\ExpedienteObservacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpedienteObservacionController extends Controller
{
    public function store(Request $request, int $expediente): RedirectResponse
    {
        $expedienteData = $this->findExpediente($expediente);

        $validated = $request->validate([
            'tipo_observacion' => ['required', 'in:falta_pdf,falta_xml,falta_valores,falta_ubicacion,datos_inconsistentes,documento_incorrecto,otro'],
            'prioridad' => ['required', 'in:baja,media,alta,critica'],
            'descripcion' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'tipo_observacion.required' => 'Debes seleccionar el tipo de observación.',
            'tipo_observacion.in' => 'El tipo de observación seleccionado no es válido.',
            'prioridad.required' => 'Debes seleccionar la prioridad.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',
            'descripcion.required' => 'Debes capturar la descripción de la observación.',
            'descripcion.min' => 'La observación debe tener al menos 5 caracteres.',
            'descripcion.max' => 'La observación no debe superar 2000 caracteres.',
        ]);

        DB::transaction(function () use ($validated, $expedienteData, $request) {
            $observacion = ExpedienteObservacion::create([
                'expediente_id' => $expedienteData->id,
                'numero_activo' => $expedienteData->numero_activo,
                'tipo_observacion' => $validated['tipo_observacion'],
                'prioridad' => $validated['prioridad'],
                'estatus' => 'abierta',
                'descripcion' => $validated['descripcion'],
                'creado_por' => auth()->id(),
                'actualizado_por' => auth()->id(),
            ]);

            DB::table('expedientes')
                ->where('id', $expedienteData->id)
                ->update([
                    'estatus' => 'observado',
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            DB::table('activos')
                ->where('numero_activo', $expedienteData->numero_activo)
                ->update([
                    'estatus_documental' => 'observado',
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $this->registrarBitacora(
                numeroActivo: $expedienteData->numero_activo,
                accion: 'OBSERVACION_CREADA',
                tablaAfectada: 'expediente_observaciones',
                registroClave: (string) $observacion->id,
                antes: null,
                despues: $observacion->toArray(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $expedienteData->id)
            ->with('success', 'La observación fue registrada y el expediente quedó en estatus observado.');
    }

    public function update(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $antes = $observacionData->toArray();

        $validated = $request->validate([
            'estatus' => ['required', 'in:abierta,en_proceso,cerrada,cancelada'],
            'prioridad' => ['required', 'in:baja,media,alta,critica'],
            'respuesta' => ['nullable', 'string', 'max:2000'],
        ], [
            'estatus.required' => 'Debes seleccionar el estatus de seguimiento.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'prioridad.required' => 'Debes seleccionar la prioridad.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',
            'respuesta.max' => 'La respuesta no debe superar 2000 caracteres.',
        ]);

        DB::transaction(function () use ($observacionData, $validated, $antes, $request) {
            $estatusFinal = in_array($validated['estatus'], ['cerrada', 'cancelada'], true);

            $observacionData->update([
                'estatus' => $validated['estatus'],
                'prioridad' => $validated['prioridad'],
                'respuesta' => $validated['respuesta'] ?? null,
                'actualizado_por' => auth()->id(),
                'cerrado_por' => $estatusFinal ? auth()->id() : null,
                'fecha_cierre' => $estatusFinal ? now() : null,
            ]);

            $this->recalcularEstatusExpediente($observacionData->expediente_id, $observacionData->numero_activo);

            $accion = match ($validated['estatus']) {
                'cerrada' => 'OBSERVACION_CERRADA',
                'cancelada' => 'OBSERVACION_CANCELADA',
                default => 'OBSERVACION_ACTUALIZADA',
            };

            $this->registrarBitacora(
                numeroActivo: $observacionData->numero_activo,
                accion: $accion,
                tablaAfectada: 'expediente_observaciones',
                registroClave: (string) $observacionData->id,
                antes: $antes,
                despues: $observacionData->fresh()->toArray(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $observacionData->expediente_id)
            ->with('success', 'El seguimiento de la observación fue actualizado correctamente.');
    }

    public function destroy(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $antes = $observacionData->toArray();

        DB::transaction(function () use ($observacionData, $antes, $request) {
            $observacionData->update([
                'estatus' => 'cancelada',
                'respuesta' => trim((string) $observacionData->respuesta) !== ''
                    ? $observacionData->respuesta
                    : 'Observación cancelada desde el detalle del expediente.',
                'actualizado_por' => auth()->id(),
                'cerrado_por' => auth()->id(),
                'fecha_cierre' => now(),
            ]);

            $this->recalcularEstatusExpediente($observacionData->expediente_id, $observacionData->numero_activo);

            $this->registrarBitacora(
                numeroActivo: $observacionData->numero_activo,
                accion: 'OBSERVACION_CANCELADA',
                tablaAfectada: 'expediente_observaciones',
                registroClave: (string) $observacionData->id,
                antes: $antes,
                despues: $observacionData->fresh()->toArray(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $observacionData->expediente_id)
            ->with('success', 'La observación fue cancelada. La trazabilidad se conserva en bitácora.');
    }

    private function findExpediente(int $expediente): object
    {
        $expedienteData = DB::table('expedientes')
            ->where('id', $expediente)
            ->first();

        abort_if(!$expedienteData, 404, 'El expediente solicitado no existe.');

        return $expedienteData;
    }

    private function recalcularEstatusExpediente(int $expedienteId, string $numeroActivo): void
    {
        $observacionesAbiertas = DB::table('expediente_observaciones')
            ->where('expediente_id', $expedienteId)
            ->whereIn('estatus', ['abierta', 'en_proceso'])
            ->exists();

        if ($observacionesAbiertas) {
            DB::table('expedientes')
                ->where('id', $expedienteId)
                ->update([
                    'estatus' => 'observado',
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            DB::table('activos')
                ->where('numero_activo', $numeroActivo)
                ->update([
                    'estatus_documental' => 'observado',
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            return;
        }

        $tiposDocumentos = DB::table('documentos_expediente')
            ->where('expediente_id', $expedienteId)
            ->where('vigente', true)
            ->pluck('tipo_documento')
            ->map(fn ($tipo) => strtoupper((string) $tipo))
            ->unique()
            ->values()
            ->all();

        $estatus = in_array('PDF', $tiposDocumentos, true) && in_array('XML', $tiposDocumentos, true)
            ? 'completo'
            : 'incompleto';

        DB::table('expedientes')
            ->where('id', $expedienteId)
            ->update([
                'estatus' => $estatus,
                'actualizado_por' => auth()->id(),
                'updated_at' => now(),
            ]);

        $activosExpedientes = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->pluck('estatus')
            ->all();

        $estatusActivo = in_array('observado', $activosExpedientes, true)
            ? 'observado'
            : (in_array('incompleto', $activosExpedientes, true) ? 'incompleto' : 'completo');

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'estatus_documental' => $estatusActivo,
                'actualizado_por' => auth()->id(),
                'updated_at' => now(),
            ]);
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        mixed $antes,
        mixed $despues,
        ?string $ip
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => auth()->id(),
            'modulo' => 'M03 Consultas, reportes y seguimiento',
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_clave' => $registroClave,
            'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $ip,
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
