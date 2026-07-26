<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $password
 *
 * @method static inStore(int $storeId)
 * @method static withRoleInStore(string|Role $role, int $storeId)
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPermissions, HasRoles, Notifiable;

    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->current();
    }

    public function getStores(): Collection
    {
        $teamIds = $this->roles()->pluck('team_id')->filter()->unique();

        return Store::query()
            ->where(function ($query) use ($teamIds) {
                $query->whereIn('id', $teamIds)
                    ->orWhere('owner_id', $this->id);
            })
            ->get();
    }

    /**
     * Scope a query to only include users in a specific store.
     */
    public function scopeInStore(Builder $query, int $storeId): Builder
    {
        return $query->whereHas('roles', function ($q) use ($storeId) {
            $q->wherePivot('team_id', $storeId);
        });
    }

    /**
     * Scope a query to only include users with a specific role in a store.
     */
    public function scopeWithRoleInStore(Builder $query, string|Role $role, int $storeId): Builder
    {
        return $query->whereHas('roles', function ($q) use ($role, $storeId) {
            $q->where('key', is_string($role) ? $role : $role->key)
                ->wherePivot('team_id', $storeId);
        });
    }

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
}
