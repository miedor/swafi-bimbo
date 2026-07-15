<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionMasivaFila extends Model
{
    protected $table = 'importacion_masiva_filas';

    protected $fillable = [
        'importacion_id',
        'numero_fila',
        'estatus',
        'accion',
        'datos',
        'errores',
        'advertencias',
        'aplicada',
        'resultado',
    ];

    protected $casts = [
        'datos' => 'array',
        'errores' => 'array',
        'advertencias' => 'array',
        'aplicada' => 'boolean',
        'resultado' => 'array',
        'numero_fila' => 'integer',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionMasiva::class, 'importacion_id');
    }
}
