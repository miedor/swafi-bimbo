<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusquedaGuardada extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'busquedas_guardadas';

    protected $fillable = [
        'user_id',
        'nombre',
        'modulo',
        'filtros',
        'deleted_by',
        'delete_reason',
    ];

    protected $casts = [
        'filtros' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
