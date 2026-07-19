<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteProgramadoEjecucion extends Model
{
    use HasFactory;

    protected $table = 'reportes_programados_ejecuciones';

    protected $fillable = [
        'reporte_programado_id',
        'scheduled_for',
        'started_at',
        'finished_at',
        'estado',
        'formato',
        'total_registros',
        'destinatarios_total',
        'destinatarios_enviados',
        'archivo_nombre',
        'archivo_sha256',
        'error_referencia',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'destinatarios_enviados' => 'array',
            'total_registros' => 'integer',
            'destinatarios_total' => 'integer',
        ];
    }

    public function reporteProgramado()
    {
        return $this->belongsTo(ReporteProgramado::class, 'reporte_programado_id');
    }
}
