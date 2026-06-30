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
    ];

    protected $casts = [
        'fecha_inventario' => 'date',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }
}
