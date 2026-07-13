<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioEvidencia extends Model
{
    protected $table = 'inventario_evidencias';

    protected $fillable = [
        'inventario_id',
        'numero_activo',
        'tipo_evidencia',
        'nombre_archivo',
        'ruta_archivo',
        'mime_type',
        'tamano_bytes',
        'hash_sha256',
        'vigente',
        'cargado_por',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'vigente' => 'boolean',
    ];

    public function inventario()
    {
        return $this->belongsTo(InventarioActivo::class, 'inventario_id');
    }

    public function usuarioCarga()
    {
        return $this->belongsTo(User::class, 'cargado_por');
    }
}
