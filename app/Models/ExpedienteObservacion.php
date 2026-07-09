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
        'fecha_validacion',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'fecha_atencion' => 'datetime',
        'fecha_validacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];
}
