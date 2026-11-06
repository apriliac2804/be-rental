<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Mode "ringkas" (untuk daftar/index):
     *   - Tidak menyertakan rental_items agar response ringan
     *
     * Mode "detail" (untuk show):
     *   - Menyertakan rental_items saat di-load via with('rentalItems.product')
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'invoice_code'  => $this->invoice_code,
            'status'        => $this->status,
            'status_label'  => $this->statusLabel(),

            // Tanggal
            'start_date'    => $this->start_date->toDateString(),
            'end_date'      => $this->end_date->toDateString(),
            'duration_days' => $this->duration_days,    // accessor

            // Harga
            'total_price'   => (float) $this->total_price,

            // Items (hanya muncul saat di-load)
            'items'         => RentalItemResource::collection(
                $this->whenLoaded('rentalItems')
            ),
            'items_count'   => $this->whenCounted('rentalItems'),

            // User (hanya muncul saat di-load — untuk admin)
            'user'          => new UserResource($this->whenLoaded('user')),

            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    private function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => '⏳ Menunggu Konfirmasi',
            'confirmed' => '✅ Dikonfirmasi',
            'active'    => '🏕️ Sedang Disewa',
            'completed' => '🎉 Selesai',
            'cancelled' => '❌ Dibatalkan',
            default     => $this->status,
        };
    }
}
