<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioActivo extends Model
{
    protected $table = 'inventarios_activo';

    protected $fillable = [
        'numero_activo',
        'fecha_inventario',
        'estatus_localizacion',
        'ubicacion_verificada_id',
        'observaciones',
        'verificado_por',
        'notificar_a',
        'notificado_at',
        'notificacion_error',
        'requiere_atencion',
    ];

    protected $casts = [
        'fecha_inventario' => 'date',
        'notificado_at' => 'datetime',
        'requiere_atencion' => 'boolean',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }

    public function evidencias()
    {
        return $this->hasMany(InventarioEvidencia::class, 'inventario_id');
    }

    public function usuarioVerificador()
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    public function usuarioNotificado()
    {
        return $this->belongsTo(User::class, 'notificar_a');
    }
}
