<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => new BookResource($this->whenLoaded('book')),
            'borrower_name' => $this->borrower_name,
            'borrowed_at' => $this->borrowed_at->toISOString(),
            'returned_at' => $this->returned_at?->toISOString(),
        ];
    }
}
