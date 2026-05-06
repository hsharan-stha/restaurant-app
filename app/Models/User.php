<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasRole(string $name): bool
    {
        return $this->roles()->where('name', $name)->exists();
    }

    public function hasAnyRole(array $names): bool
    {
        return $this->roles()->whereIn('name', $names)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }
}
