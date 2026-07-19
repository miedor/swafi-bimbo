<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionCatalogoFila extends Model
{
    protected $table = 'importacion_catalogo_filas';

    protected $fillable = [
        'importacion_id',
        'numero_fila',
        'estatus',
        'accion',
        'registro_id',
        'datos',
        'errores',
        'advertencias',
        'aplicada',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'errores' => 'array',
            'advertencias' => 'array',
            'aplicada' => 'boolean',
            'resultado' => 'array',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCatalogo::class, 'importacion_id');
    }
}
