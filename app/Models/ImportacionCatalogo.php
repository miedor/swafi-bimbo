<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionCatalogo extends Model
{
    protected $table = 'importaciones_catalogo';

    protected $fillable = [
        'uuid',
        'user_id',
        'catalogo',
        'estado',
        'archivo_nombre_original',
        'archivo_extension',
        'archivo_hash_sha256',
        'total_filas',
        'filas_aceptadas',
        'filas_observadas',
        'filas_rechazadas',
        'filas_insertadas',
        'filas_actualizadas',
        'resumen',
        'aplicada_at',
        'cancelada_at',
        'expira_at',
    ];

    protected function casts(): array
    {
        return [
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
        return $this->hasMany(ImportacionCatalogoFila::class, 'importacion_id');
    }

    public function puedeAplicarse(): bool
    {
        return $this->estado === 'previsualizada'
            && ($this->expira_at === null || $this->expira_at->isFuture())
            && ((int) $this->filas_aceptadas + (int) $this->filas_observadas) > 0;
    }
}
