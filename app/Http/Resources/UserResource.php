<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Field yang disertakan:
     *   - id, name, email, role, created_at
     *
     * Field yang TIDAK disertakan (keamanan):
     *   - password, remember_token, email_verified_at
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
