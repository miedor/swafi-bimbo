<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activo extends Model
{
    protected $table = 'activos';

    protected $primaryKey = 'numero_activo';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'numero_activo',
        'tipo_activo_id',
        'proveedor_id',
        'centro_costo_id',
        'planta_id',
        'ubicacion_id',
        'responsable_id',
        'descripcion',
        'serie',
        'marca',
        'modelo',
        'fecha_adquisicion',
        'estatus_operativo',
        'estatus_documental',
        'activo',
        'creado_por',
        'actualizado_por',
    ];

    public function expedientes()
    {
        return $this->hasMany(Expediente::class, 'numero_activo', 'numero_activo');
    }
}
