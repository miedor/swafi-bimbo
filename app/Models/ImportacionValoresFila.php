<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionValoresFila extends Model
{
    protected $table = 'importacion_valores_filas';

    protected $fillable = [
        'importacion_id',
        'numero_fila',
        'numero_activo',
        'estatus',
        'accion',
        'datos',
        'errores',
        'aplicada',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'numero_fila' => 'integer',
            'datos' => 'array',
            'errores' => 'array',
            'aplicada' => 'boolean',
            'resultado' => 'array',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionValores::class, 'importacion_id');
    }
}
