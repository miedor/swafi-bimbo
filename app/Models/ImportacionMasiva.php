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
        'reversion_disponible_hasta',
        'revertida_at',
        'revertida_por',
        'motivo_reversion',
        'reversion_resumen',
        'cancelada_at',
        'expira_at',
    ];

    protected $casts = [
        'resumen' => 'array',
        'reversion_resumen' => 'array',
        'aplicada_at' => 'datetime',
        'reversion_disponible_hasta' => 'datetime',
        'revertida_at' => 'datetime',
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

    public function usuarioReversion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertida_por');
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'previsualizada'
            && (!$this->expira_at || $this->expira_at->isFuture());
    }

    public function esRevertible(): bool
    {
        return $this->estado === 'aplicada'
            && $this->reversion_disponible_hasta?->isFuture() === true
            && data_get($this->resumen, 'reversion.disponible') === true;
    }

    public function motivoNoRevertible(): ?string
    {
        if ($this->estado === 'revertida') {
            return 'El lote ya fue revertido.';
        }

        if ($this->estado !== 'aplicada') {
            return 'Solo los lotes aplicados pueden revertirse.';
        }

        if (!$this->reversion_disponible_hasta) {
            return 'El lote no contiene una instantánea de reversión compatible con HU-029.';
        }

        if ($this->reversion_disponible_hasta->isPast()) {
            return 'La ventana autorizada de reversión ya terminó.';
        }

        if (data_get($this->resumen, 'reversion.disponible') !== true) {
            $reason = trim((string) data_get(
                $this->resumen,
                'reversion.motivo',
                ''
            ));

            return $reason !== ''
                ? $reason
                : 'No fue posible consolidar la instantánea posterior a la aplicación.';
        }

        return null;
    }
}
