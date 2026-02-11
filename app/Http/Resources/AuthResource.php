<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    public function __construct(
        public mixed $user,
        public ?string $token = null
    ) {
        // مهم: JsonResource لازم يستقبل resource
        parent::__construct($user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'token' => $this->token,
            'token_type' => $this->token ? 'Bearer' : null,
        ];
    }
}
