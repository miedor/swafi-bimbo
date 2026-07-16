<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoInventario extends Model
{
    protected $table = 'periodos_inventario';

    protected $fillable = [
        'uuid',
        'planta_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estatus',
        'observaciones',
        'motivo_bloqueo',
        'creado_por',
        'bloqueado_por',
        'bloqueado_at',
        'desbloqueado_por',
        'desbloqueado_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'bloqueado_at' => 'datetime',
        'desbloqueado_at' => 'datetime',
    ];


    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function usuarioBloqueo()
    {
        return $this->belongsTo(User::class, 'bloqueado_por');
    }

    public function usuarioDesbloqueo()
    {
        return $this->belongsTo(User::class, 'desbloqueado_por');
    }
}
