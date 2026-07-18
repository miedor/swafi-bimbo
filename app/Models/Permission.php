<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'clave',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_sistema' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
