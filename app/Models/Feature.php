<?php

namespace App\Models;

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
 * @property-read string $permission_key
 * @property-read bool $is_active
 *
 * @method static Builder|Feature active()
 */
#[Fillable(['name', 'key', 'description', 'permission_key', 'is_active'])]
class Feature extends Model
{
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_feature');
    }

    public function getPermission(): ?Permission
    {
        return Permission::where('key', $this->permission_key)->first();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
