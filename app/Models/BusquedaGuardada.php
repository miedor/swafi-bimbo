<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusquedaGuardada extends Model
{
    use HasFactory;

    protected $table = 'busquedas_guardadas';

    protected $fillable = [
        'user_id',
        'nombre',
        'modulo',
        'filtros',
    ];

    protected $casts = [
        'filtros' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
