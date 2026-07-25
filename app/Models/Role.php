<?php

namespace App\Models;

use App\Models\Concerns\HasPermissions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $key
 */
#[Fillable(['name', 'key'])]
class Role extends Model
{
    use HasPermissions;

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
