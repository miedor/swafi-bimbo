<?php

namespace App\Http\Controllers;

use App\Models\ExpedienteObservacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpedienteObservacionController extends Controller
{
    public function store(Request $request, int $expediente): RedirectResponse
    {
        $expedienteData = $this->findExpediente($expediente);

        $validated = $request->validate([
            'tipo_observacion' => ['required', 'in:falta_pdf,falta_xml,falta_valores,falta_ubicacion,ubicacion_incorrecta,datos_inconsistentes,documento_incorrecto,otro'],
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

        if (!$this->canCreateObservation($validated['tipo_observacion'])) {
            return $this->redirectDenied($expedienteData->id, 'Tu perfil no puede registrar este tipo de observación.');
        }

        $existing = ExpedienteObservacion::query()
            ->where('expediente_id', $expedienteData->id)
            ->where('tipo_observacion', $validated['tipo_observacion'])
            ->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])
            ->exists();

        if ($existing) {
            return redirect()
                ->route('expediente', $expedienteData->id)
                ->withErrors([
                    'observaciones' => 'Ya existe una observación activa de ese tipo para el expediente. Atiéndela o ciérrala antes de generar otra igual.',
                ]);
        }

        DB::transaction(function () use ($validated, $expedienteData, $request) {
            $observacion = ExpedienteObservacion::create([
                'expediente_id' => $expedienteData->id,
                'numero_activo' => $expedienteData->numero_activo,
                'tipo_observacion' => $validated['tipo_observacion'],
                'prioridad' => $validated['prioridad'],
                'estatus' => 'abierta',
                'descripcion' => $validated['descripcion'],
                'creado_por' => $this->userId(),
                'actualizado_por' => $this->userId(),
            ]);

            $this->markExpedienteObserved($expedienteData->id, $expedienteData->numero_activo);

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
            ->with('success', 'La observación fue registrada. Ahora debe ser atendida por un perfil operativo distinto y validada por Consulta/Auditoría.');
    }

    public function tomar(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $expedienteData = $this->findExpediente($observacionData->expediente_id);

        if (!$this->canAttendObservation($observacionData)) {
            return $this->redirectDenied($expedienteData->id, 'Tu perfil no puede tomar en atención esta observación.');
        }

        if (!$this->canCurrentUserAttendOwnObservation($observacionData)) {
            return $this->redirectDenied($expedienteData->id, 'No puedes atender una observación que tú mismo registraste. Debe existir validación cruzada entre perfiles.');
        }

        if (!in_array($observacionData->estatus, ['abierta', 'rechazada'], true)) {
            return $this->redirectDenied($expedienteData->id, 'Solo las observaciones abiertas o rechazadas pueden tomarse en atención.');
        }

        $antes = $observacionData->toArray();

        DB::transaction(function () use ($observacionData, $antes, $request) {
            $observacionData->update([
                'estatus' => 'en_atencion',
                'atendido_por' => $this->userId(),
                'actualizado_por' => $this->userId(),
                'fecha_atencion' => now(),
            ]);

            $this->markExpedienteObserved($observacionData->expediente_id, $observacionData->numero_activo);

            $this->registrarBitacora(
                numeroActivo: $observacionData->numero_activo,
                accion: 'OBSERVACION_EN_ATENCION',
                tablaAfectada: 'expediente_observaciones',
                registroClave: (string) $observacionData->id,
                antes: $antes,
                despues: $observacionData->fresh()->toArray(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $observacionData->expediente_id)
            ->with('success', 'La observación quedó en atención. Cuando termines la corrección, marca la observación como atendida.');
    }

    public function atender(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $expedienteData = $this->findExpediente($observacionData->expediente_id);

        if (!$this->canAttendObservation($observacionData)) {
            return $this->redirectDenied($expedienteData->id, 'Tu perfil no puede atender esta observación.');
        }

        if (!$this->canCurrentUserAttendOwnObservation($observacionData)) {
            return $this->redirectDenied($expedienteData->id, 'No puedes atender una observación que tú mismo registraste. Debe existir validación cruzada entre perfiles.');
        }

        if (!in_array($observacionData->estatus, ['abierta', 'en_atencion', 'rechazada'], true)) {
            return $this->redirectDenied($expedienteData->id, 'La observación no se encuentra en un estatus atendible.');
        }

        $validated = $request->validate([
            'respuesta_atencion' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'respuesta_atencion.required' => 'Debes documentar la corrección realizada.',
            'respuesta_atencion.min' => 'La respuesta de atención debe tener al menos 5 caracteres.',
            'respuesta_atencion.max' => 'La respuesta de atención no debe superar 2000 caracteres.',
        ]);

        $antes = $observacionData->toArray();

        DB::transaction(function () use ($observacionData, $validated, $antes, $request) {
            $observacionData->update([
                'estatus' => 'atendida',
                'respuesta_atencion' => $validated['respuesta_atencion'],
                'atendido_por' => $this->userId(),
                'actualizado_por' => $this->userId(),
                'fecha_atencion' => now(),
            ]);

            $this->markExpedienteObserved($observacionData->expediente_id, $observacionData->numero_activo);

            $this->registrarBitacora(
                numeroActivo: $observacionData->numero_activo,
                accion: 'OBSERVACION_ATENDIDA',
                tablaAfectada: 'expediente_observaciones',
                registroClave: (string) $observacionData->id,
                antes: $antes,
                despues: $observacionData->fresh()->toArray(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $observacionData->expediente_id)
            ->with('success', 'La observación fue marcada como atendida. Ahora debe ser validada por Consulta/Auditoría.');
    }

    public function validar(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $expedienteData = $this->findExpediente($observacionData->expediente_id);

        if (!$this->canValidateObservation()) {
            return $this->redirectDenied($expedienteData->id, 'Tu perfil no puede validar o cerrar observaciones.');
        }

        if (!$this->canCurrentUserValidateOwnAttention($observacionData)) {
            return $this->redirectDenied($expedienteData->id, 'No puedes validar una corrección atendida por tu mismo usuario. Debe existir validación cruzada.');
        }

        if ($observacionData->estatus !== 'atendida') {
            return $this->redirectDenied($expedienteData->id, 'Solo se pueden validar observaciones con estatus atendida.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:cerrada,rechazada'],
            'comentario_validacion' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'decision.required' => 'Debes seleccionar si la corrección se cierra o se rechaza.',
            'decision.in' => 'La decisión seleccionada no es válida.',
            'comentario_validacion.required' => 'Debes capturar el comentario de validación.',
            'comentario_validacion.min' => 'El comentario de validación debe tener al menos 5 caracteres.',
            'comentario_validacion.max' => 'El comentario de validación no debe superar 2000 caracteres.',
        ]);

        $antes = $observacionData->toArray();

        DB::transaction(function () use ($observacionData, $validated, $antes, $request) {
            $observacionData->update([
                'estatus' => $validated['decision'],
                'comentario_validacion' => $validated['comentario_validacion'],
                'validado_por' => $this->userId(),
                'actualizado_por' => $this->userId(),
                'fecha_validacion' => now(),
            ]);

            $this->recalcularEstatusExpediente($observacionData->expediente_id, $observacionData->numero_activo);

            $accion = $validated['decision'] === 'cerrada'
                ? 'OBSERVACION_CERRADA'
                : 'OBSERVACION_RECHAZADA';

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

        $message = $validated['decision'] === 'cerrada'
            ? 'La observación fue cerrada por Consulta/Auditoría.'
            : 'La corrección fue rechazada y regresa a atención del área operativa.';

        return redirect()
            ->route('expediente', $observacionData->expediente_id)
            ->with('success', $message);
    }

    public function cancelar(Request $request, int $observacion): RedirectResponse
    {
        $observacionData = ExpedienteObservacion::findOrFail($observacion);
        $expedienteData = $this->findExpediente($observacionData->expediente_id);

        if (!$this->canValidateObservation()) {
            return $this->redirectDenied($expedienteData->id, 'Tu perfil no puede cancelar observaciones.');
        }

        if (in_array($observacionData->estatus, ['cerrada', 'cancelada'], true)) {
            return $this->redirectDenied($expedienteData->id, 'La observación ya se encuentra cerrada o cancelada.');
        }

        $validated = $request->validate([
            'comentario_validacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'comentario_validacion.max' => 'El comentario no debe superar 2000 caracteres.',
        ]);

        $antes = $observacionData->toArray();

        DB::transaction(function () use ($observacionData, $validated, $antes, $request) {
            $observacionData->update([
                'estatus' => 'cancelada',
                'comentario_validacion' => $validated['comentario_validacion'] ?: 'Observación cancelada desde el flujo de validación cruzada.',
                'cancelado_por' => $this->userId(),
                'actualizado_por' => $this->userId(),
                'fecha_cancelacion' => now(),
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

    private function canCreateObservation(string $tipoObservacion): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isConsultaAuditoria()) {
            return true;
        }

        if ($this->isPlantaInventarios()) {
            return in_array($tipoObservacion, ['falta_ubicacion', 'ubicacion_incorrecta'], true);
        }

        return false;
    }

    private function canAttendObservation(ExpedienteObservacion $observacion): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isCaptura()) {
            return true;
        }

        if ($this->isPlantaInventarios()) {
            return in_array($observacion->tipo_observacion, ['falta_ubicacion', 'ubicacion_incorrecta'], true);
        }

        return false;
    }

    private function canValidateObservation(): bool
    {
        return $this->isAdmin() || $this->isConsultaAuditoria();
    }

    private function canCurrentUserAttendOwnObservation(ExpedienteObservacion $observacion): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (int) $observacion->creado_por !== $this->userId();
    }

    private function canCurrentUserValidateOwnAttention(ExpedienteObservacion $observacion): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (int) $observacion->atendido_por !== $this->userId();
    }

    private function markExpedienteObserved(int $expedienteId, string $numeroActivo): void
    {
        DB::table('expedientes')
            ->where('id', $expedienteId)
            ->update([
                'estatus' => 'observado',
                'actualizado_por' => $this->userId(),
                'updated_at' => now(),
            ]);

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'estatus_documental' => 'observado',
                'actualizado_por' => $this->userId(),
                'updated_at' => now(),
            ]);
    }

    private function recalcularEstatusExpediente(int $expedienteId, string $numeroActivo): void
    {
        $observacionesActivas = DB::table('expediente_observaciones')
            ->where('expediente_id', $expedienteId)
            ->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])
            ->exists();

        if ($observacionesActivas) {
            $this->markExpedienteObserved($expedienteId, $numeroActivo);
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

        $estatusExpediente = in_array('PDF', $tiposDocumentos, true) && in_array('XML', $tiposDocumentos, true)
            ? 'completo'
            : 'incompleto';

        DB::table('expedientes')
            ->where('id', $expedienteId)
            ->update([
                'estatus' => $estatusExpediente,
                'actualizado_por' => $this->userId(),
                'updated_at' => now(),
            ]);

        $estatusExpedientesActivo = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->pluck('estatus')
            ->map(fn ($estatus) => (string) $estatus)
            ->all();

        $estatusActivo = in_array('observado', $estatusExpedientesActivo, true)
            ? 'observado'
            : (in_array('incompleto', $estatusExpedientesActivo, true) ? 'incompleto' : 'completo');

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'estatus_documental' => $estatusActivo,
                'actualizado_por' => $this->userId(),
                'updated_at' => now(),
            ]);
    }

    private function redirectDenied(int $expedienteId, string $message): RedirectResponse
    {
        return redirect()
            ->route('expediente', $expedienteId)
            ->withErrors([
                'observaciones' => $message,
            ]);
    }

    private function roles(): array
    {
        return session('swafi_roles', []);
    }

    private function isAdmin(): bool
    {
        return in_array('Administrador SWAFI', $this->roles(), true)
            || in_array('Administrador', $this->roles(), true);
    }

    private function isCaptura(): bool
    {
        return in_array('Usuario Captura', $this->roles(), true)
            || in_array('Capturista', $this->roles(), true);
    }

    private function isConsultaAuditoria(): bool
    {
        return in_array('Usuario Consulta / Auditoría', $this->roles(), true)
            || in_array('Usuario Consulta / Auditoria', $this->roles(), true)
            || in_array('Consultor', $this->roles(), true)
            || in_array('Auditor', $this->roles(), true);
    }

    private function isPlantaInventarios(): bool
    {
        return in_array('Usuario Planta / Inventarios', $this->roles(), true);
    }

    private function userId(): ?int
    {
        return session('swafi_user_id') ?: auth()->id();
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
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => $this->userId(),
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
        } catch (\Throwable $exception) {
            // El flujo de observaciones no debe bloquearse por error de bitácora.
        }
    }
}
