<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReporteProgramado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reportes_programados';

    protected $fillable = [
        'reporte_guardado_id',
        'user_id',
        'frecuencia',
        'dia_semana',
        'dia_mes',
        'hora_local',
        'zona_horaria',
        'formato',
        'destinatarios',
        'activo',
        'proxima_ejecucion_at',
        'ultima_ejecucion_at',
        'ultimo_estado',
        'ultimo_error_referencia',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'activo' => 'boolean',
            'dia_semana' => 'integer',
            'dia_mes' => 'integer',
            'proxima_ejecucion_at' => 'datetime',
            'ultima_ejecucion_at' => 'datetime',
        ];
    }

    public function reporteGuardado()
    {
        return $this->belongsTo(ReporteGuardado::class, 'reporte_guardado_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ejecuciones()
    {
        return $this->hasMany(ReporteProgramadoEjecucion::class, 'reporte_programado_id');
    }
}
