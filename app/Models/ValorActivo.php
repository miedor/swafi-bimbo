<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ValorActivo extends Model
{
    use SoftDeletes;

    protected $table = 'valores_activo';

    protected $fillable = [
        'numero_activo',
        'valor_fiscal',
        'valor_financiero',
        'moneda',
        'tipo_cambio',
        'fecha_tipo_cambio',
        'origen_tipo_cambio',
        'depreciacion_acumulada',
        'valor_en_libros',
        'vida_util_meses',
        'estatus_contable',
        'motivo_cambio',
        'cfdi_validacion_id',
        'conciliacion_cfdi',
        'conciliacion_detalle',
        'fecha_corte',
        'registrado_por',
        'deleted_by',
        'delete_reason',
    ];

    protected $casts = [
        'valor_fiscal' => 'decimal:2',
        'valor_financiero' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
        'fecha_tipo_cambio' => 'date',
        'depreciacion_acumulada' => 'decimal:2',
        'valor_en_libros' => 'decimal:2',
        'fecha_corte' => 'date',
        'conciliacion_detalle' => 'array',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }

    public function cfdiValidacion()
    {
        return $this->belongsTo(CfdiValidacion::class, 'cfdi_validacion_id');
    }
}
