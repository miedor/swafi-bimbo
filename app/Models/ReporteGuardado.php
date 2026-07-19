<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReporteGuardado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reportes_guardados';

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo_reporte',
        'filtros',
        'columnas',
        'orientacion',
        'deleted_by',
        'delete_reason',
    ];

    protected $casts = [
        'filtros' => 'array',
        'columnas' => 'array',
    ];

    public function programacion()
    {
        return $this->hasOne(ReporteProgramado::class, 'reporte_guardado_id');
    }
}
