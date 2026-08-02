<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use App\Enums\PermissionContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $key
 * @property-read string|null $description
 * @property-read string|null $permission_key
 * @property-read string $context
 * @property-read string $status
 * @property-read bool $is_active
 *
 * @method static Builder|Feature active()
 */
#[Fillable(['name', 'key', 'description', 'permission_key', 'context', 'status'])]
class Feature extends Model
{
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_feature')
            ->withPivot('is_trial_allowed')
            ->withTimestamps();
    }

    /**
     * The permission that guards access to this feature.
     */
    public function getPermission(): ?Permission
    {
        return $this->permission_key
            ? Permission::where('key', $this->permission_key)->first()
            : null;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', FeatureStatus::ACTIVE->value);
    }

    public function isActive(): bool
    {
        return $this->status === FeatureStatus::ACTIVE->value;
    }

    /**
     * Read-only compatibility accessor for existing resources/code.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function isStoreScoped(): bool
    {
        return $this->context === PermissionContext::STORE->value;
    }
}
