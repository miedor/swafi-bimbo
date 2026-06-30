<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoUbicacion extends Model
{
    protected $table = 'movimientos_ubicacion';

    protected $fillable = [
        'numero_activo',
        'ubicacion_origen_id',
        'ubicacion_destino_id',
        'motivo',
        'evidencia',
        'fecha_movimiento',
        'responsable_id',
        'registrado_por',
    ];

    protected $casts = [
        'fecha_movimiento' => 'datetime',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }
}
