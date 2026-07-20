<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpedienteObservacion extends Model
{
    use HasFactory;

    protected $table = 'expediente_observaciones';

    protected $fillable = [
        'expediente_id',
        'numero_activo',
        'tipo_observacion',
        'prioridad',
        'rol_destino',
        'asignado_a',
        'estatus',
        'descripcion',
        'respuesta_atencion',
        'comentario_validacion',
        'creado_por',
        'atendido_por',
        'validado_por',
        'cancelado_por',
        'actualizado_por',
        'fecha_atencion',
        'fecha_asignacion',
        'fecha_compromiso',
        'fecha_validacion',
        'fecha_cancelacion',
        'fecha_notificacion',
        'ultimo_intento_recordatorio_at',
        'fecha_ultimo_recordatorio',
        'recordatorios_enviados',
        'recordatorio_error_referencia',
        'notificacion_error',
    ];

    protected $casts = [
        'fecha_atencion' => 'datetime',
        'fecha_asignacion' => 'datetime',
        'fecha_compromiso' => 'date:Y-m-d',
        'fecha_validacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'fecha_notificacion' => 'datetime',
        'ultimo_intento_recordatorio_at' => 'datetime',
        'fecha_ultimo_recordatorio' => 'datetime',
        'recordatorios_enviados' => 'integer',
    ];
}
