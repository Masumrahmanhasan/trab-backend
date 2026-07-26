<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */

    use HasFactory, Notifiable, HasApiTokens, HasPermissions, HasRoles;


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedStores()
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function getStores(): \Illuminate\Support\Collection
    {
        // Get all unique team_ids from user's role assignments
        return Store::whereIn('id', $this->roles()->pluck('team_id')->filter())
            ->orWhere('owner_id', $this->id)
            ->get();
    }

    public function scopeInStore($query, $storeId)
    {
        return $query->whereHas('roles', function ($q) use ($storeId) {
            $q->wherePivot('team_id', $storeId);
        });
    }

    public function scopeWithRoleInStore($query, $role, $storeId)
    {
        return $query->whereHas('roles', function ($q) use ($role, $storeId) {
            $q->where('key', is_string($role) ? $role : $role->key)
              ->wherePivot('team_id', $storeId);
        });
    }
}
