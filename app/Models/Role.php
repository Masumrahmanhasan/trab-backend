<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $key
 * @property-read null|CarbonImmutable $created_at
 * @property-read null|CarbonImmutable $updated_at
 */
#[Fillable(['name', 'key'])]
class Role extends Model
{
    use HasPermissions;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            'model_has_roles',
            'role_id',
            'model_id',
        );
    }
}
