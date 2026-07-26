<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read int $owner_id
 * @property-read int|null $plan_id
 */
#[Fillable(['name', 'slug', 'description', 'owner_id', 'plan_id'])]
class Store extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function staff()
    {
        return User::whereHas('roles', function ($query) {
            $query->wherePivot('team_id', $this->id);
        })->orWhere('id', $this->owner_id)->get();
    }

    public function getAvailablePermissions()
    {
        if (!$this->plan) {
            return collect();
        }

        return $this->plan->features->map(function ($feature) {
            return $feature->getPermission();
        })->filter();
    }
}
