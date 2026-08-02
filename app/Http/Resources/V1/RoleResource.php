<?php

namespace App\Http\Resources\V1;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Role $resource
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'role',
            'id' => $this->resource->id,
            'attributes' => [
                'name' => $this->resource->name,
                'key' => $this->resource->key,
                'createdAt' => $this->resource->created_at,
                'updatedAt' => $this->resource->updated_at,
            ],
            'includes' => [
                'permissions' => PermissionResource::collection($this->resource->permissions),
            ],
            'links' => [
                ['self' => route('roles.show', $this->resource->id)],
            ],
        ];
    }
}
