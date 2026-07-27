<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $request->id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'owner_id' => $request->owner_id,
            'plan_id' => $request->plan_id,
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }
}
