<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    protected $table = 'expedientes';

    protected $fillable = [
        'numero_activo',
        'folio_factura',
        'uuid_cfdi',
        'fecha_factura',
        'monto_factura',
        'moneda',
        'estatus',
        'observaciones',
        'creado_por',
        'actualizado_por',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }

    public function documentos()
    {
        return $this->hasMany(DocumentoExpediente::class, 'expediente_id');
    }
}
