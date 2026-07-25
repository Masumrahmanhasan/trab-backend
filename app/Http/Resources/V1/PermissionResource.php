<?php

namespace App\Http\Resources\V1;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Permission $resource
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'permission',
            'id' => $this->resource->id,
            'attributes' => [
                'name' => $this->resource->name,
                'key' => $this->resource->key,
                'createdAt' => $this->resource->created_at,
                'updatedAt' => $this->resource->updated_at,
            ],
            'links' => [
                ['self' => route('permissions.show', $this->resource->id)]
            ]
        ];
    }
}
