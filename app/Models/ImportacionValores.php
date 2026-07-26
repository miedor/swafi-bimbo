<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionValores extends Model
{
    protected $table = 'importaciones_valores';

    protected $fillable = [
        'uuid',
        'user_id',
        'estado',
        'archivo_nombre_original',
        'archivo_hash_sha256',
        'total_filas',
        'filas_correctas',
        'filas_incorrectas',
        'filas_insertadas',
        'filas_actualizadas',
        'filas_restauradas',
        'resumen',
        'aplicada_at',
        'cancelada_at',
        'expira_at',
    ];

    protected function casts(): array
    {
        return [
            'total_filas' => 'integer',
            'filas_correctas' => 'integer',
            'filas_incorrectas' => 'integer',
            'filas_insertadas' => 'integer',
            'filas_actualizadas' => 'integer',
            'filas_restauradas' => 'integer',
            'resumen' => 'array',
            'aplicada_at' => 'datetime',
            'cancelada_at' => 'datetime',
            'expira_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(ImportacionValoresFila::class, 'importacion_id');
    }

    public function puedeAplicarse(): bool
    {
        return $this->estado === 'previsualizada'
            && ($this->expira_at === null || $this->expira_at->isFuture())
            && $this->filas_correctas > 0;
    }
}
