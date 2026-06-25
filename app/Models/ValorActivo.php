<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValorActivo extends Model
{
    protected $table = 'valores_activo';

    protected $fillable = [
        'numero_activo',
        'valor_fiscal',
        'valor_financiero',
        'depreciacion_acumulada',
        'valor_en_libros',
        'vida_util_meses',
        'estatus_contable',
        'fecha_corte',
        'registrado_por',
    ];

    protected $casts = [
        'valor_fiscal' => 'decimal:2',
        'valor_financiero' => 'decimal:2',
        'depreciacion_acumulada' => 'decimal:2',
        'valor_en_libros' => 'decimal:2',
        'fecha_corte' => 'date',
    ];

    public function activo()
    {
        return $this->belongsTo(Activo::class, 'numero_activo', 'numero_activo');
    }
}
