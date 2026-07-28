<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // The $request parameter is required by the parent class but not used
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'store_id' => $this->store_id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'metadata' => $this->metadata,
            'is_active' => $this->isActive(),
            'is_cancelled' => $this->isCancelled(),
            'is_on_trial' => $this->isOnTrial(),
            'is_expired' => $this->isExpired(),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'store' => StoreResource::make($this->whenLoaded('store')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
