<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Field yang disertakan:
     *  - Data produk lengkap
     *  - category (saat di-load dengan withRelationship)
     *  - available_stock (stok nyata — dihitung dari luar via append)
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'price_per_day' => (float) $this->price_per_day,
            'stock'         => $this->stock,
            'description'   => $this->description,
            'image_url'     => $this->image
                                    ? asset('storage/' . $this->image)
                                    : null,
            'status'        => $this->status,
            'category'      => new CategoryResource($this->whenLoaded('category')),

            // Hanya muncul jika controller menyertakannya (availability check)
            'available_stock' => $this->when(
                isset($this->available_stock),
                fn () => $this->available_stock
            ),

            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
