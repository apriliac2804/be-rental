<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product'     => new ProductResource($this->whenLoaded('product')),
            'quantity'    => $this->quantity,
            'price'       => (float) $this->price,       // snapshot harga saat transaksi
            'subtotal'    => (float) $this->subtotal,    // accessor: qty × price
        ];
    }
}
