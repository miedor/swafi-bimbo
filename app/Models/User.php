<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'usuario',
        'name',
        'email',
        'password',
        'estatus',
        'ultimo_acceso',
        'ultimo_ip',
        'avatar_path',
        'avatar_disk',
        'avatar_mime',
        'password_changed_at',
        'intentos_fallidos',
        'ultimo_intento_fallido',
        'bloqueado_en',
        'bloqueado_motivo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password_changed_at' => 'datetime',
            'ultimo_intento_fallido' => 'datetime',
            'bloqueado_en' => 'datetime',
            'intentos_fallidos' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()
            ->where('nombre', $roleName)
            ->exists();
    }
}
