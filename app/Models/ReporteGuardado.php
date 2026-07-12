<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteGuardado extends Model
{
    use HasFactory;

    protected $table = 'reportes_guardados';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo_reporte',
        'filtros',
        'columnas',
        'orientacion',
    ];

    protected $casts = [
        'filtros' => 'array',
        'columnas' => 'array',
    ];
}
