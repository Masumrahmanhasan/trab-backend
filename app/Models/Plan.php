<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read float $price
 * @property-read string $billing_cycle
 * @property-read bool $is_active
 * @property-read int $max_stores
 * @property-read int $max_staff_per_store
 */
#[Fillable(['name', 'slug', 'description', 'price', 'billing_cycle', 'is_active', 'max_stores', 'max_staff_per_store'])]
class Plan extends Model
{
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature');
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }
}
