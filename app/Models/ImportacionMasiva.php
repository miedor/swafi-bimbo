<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionMasiva extends Model
{
    protected $table = 'importaciones_masivas';

    protected $fillable = [
        'uuid',
        'user_id',
        'estado',
        'csv_nombre_original',
        'csv_storage_disk',
        'csv_ruta',
        'csv_hash_sha256',
        'zip_nombre_original',
        'zip_storage_disk',
        'zip_ruta',
        'zip_hash_sha256',
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

    protected $casts = [
        'resumen' => 'array',
        'aplicada_at' => 'datetime',
        'cancelada_at' => 'datetime',
        'expira_at' => 'datetime',
        'total_filas' => 'integer',
        'filas_aceptadas' => 'integer',
        'filas_observadas' => 'integer',
        'filas_rechazadas' => 'integer',
        'filas_insertadas' => 'integer',
        'filas_actualizadas' => 'integer',
    ];

    public function filas(): HasMany
    {
        return $this->hasMany(ImportacionMasivaFila::class, 'importacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'previsualizada'
            && (!$this->expira_at || $this->expira_at->isFuture());
    }
}
