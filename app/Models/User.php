<?php

namespace App\Models;

use App\Enums\Roles;
use App\Models\Concerns\HasPermissions;
use App\Models\Concerns\HasRoles;
use App\Services\ActivityLogger;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
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
 * @method static Builder inStore(int $storeId)
 * @method static Builder withRoleInStore(string|Role $role, int $storeId)
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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

    /**
     * Every store the user owns OR holds a store-scoped role in.
     */
    public function getStores(): Collection
    {
        $teamIds = $this->roles()
            ->withPivot('team_id')
            ->get()
            ->pluck('pivot.team_id')
            ->filter()
            ->unique();

        return Store::query()
            ->where(function (Builder $query) use ($teamIds) {
                $query->whereIn('id', $teamIds)
                    ->orWhere('owner_id', $this->id);
            })
            ->get();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->current();
    }

    /**
     * Stores this user owns.
     */
    public function ownedStoreIds(): Collection
    {
        return $this->ownedStores()->pluck('id');
    }

    #[Scope]
    protected function inStore(Builder $query, int $storeId): void
    {
        $query->whereHas('roles', function (Builder $q) use ($storeId) {
            $q->wherePivot('team_id', $storeId);
        });
    }

    #[Scope]
    protected function withRoleInStore(Builder $query, string|Role $role, int $storeId): void
    {
        $query->whereHas('roles', function (Builder $q) use ($role, $storeId) {
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

    /**
     * Every newly created user gets the basic platform "user" role, no matter
     * which flow created them (registration, factory, admin panel, seeder).
     *
     * The existence guard keeps user creation safe before roles have been
     * seeded (e.g. isolated unit tests).
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (Role::query()->where('key', Roles::USER->value)->exists()) {
                $user->assignRole(Roles::USER->value);
            }
        });
    }

    /**
     * Log permission grant activity
     */
    protected function logPermissionGrant(string|Permission $permission, ?int $teamId = null): void
    {
        $permissionKey = $permission instanceof Permission ? $permission->key : $permission;
        app(ActivityLogger::class)->logPermissionGrant($this, $permissionKey, $teamId);
    }

    /**
     * Log permission revocation activity
     */
    protected function logPermissionRevocation(string|Permission $permission, ?int $teamId = null): void
    {
        $permissionKey = $permission instanceof Permission ? $permission->key : $permission;
        app(ActivityLogger::class)->logPermissionRevocation($this, $permissionKey, $teamId);
    }
}
