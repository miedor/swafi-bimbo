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
        'respuesta',
        'creado_por',
        'actualizado_por',
        'cerrado_por',
        'fecha_cierre',
    ];

    protected $casts = [
        'fecha_cierre' => 'datetime',
    ];
}
