<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'status'          => $this->status?->value,
            'deadline'        => $this->deadline?->toIso8601String(),
            'deadline_status' => $this->deadline_status?->value,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
