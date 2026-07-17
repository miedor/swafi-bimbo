<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudTraslado extends Model
{
    protected $table = 'solicitudes_traslado';

    protected $fillable = [
        'uuid',
        'numero_activo',
        'ubicacion_origen_id',
        'ubicacion_destino_id',
        'responsable_destino_id',
        'aprobador_asignado_id',
        'fecha_movimiento',
        'motivo',
        'evidencia',
        'estatus',
        'solicitado_por',
        'solicitado_at',
        'notificacion_aprobador_at',
        'ultimo_intento_notificacion_at',
        'notificacion_aprobador_intentos',
        'notificacion_aprobador_error',
        'resuelto_por',
        'resuelto_at',
        'comentario_resolucion',
        'movimiento_id',
    ];

    protected $casts = [
        'fecha_movimiento' => 'datetime',
        'solicitado_at' => 'datetime',
        'notificacion_aprobador_at' => 'datetime',
        'ultimo_intento_notificacion_at' => 'datetime',
        'notificacion_aprobador_intentos' => 'integer',
        'resuelto_at' => 'datetime',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobadorAsignado()
    {
        return $this->belongsTo(User::class, 'aprobador_asignado_id');
    }

    public function resolutor()
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function movimiento()
    {
        return $this->belongsTo(MovimientoUbicacion::class, 'movimiento_id');
    }
}
