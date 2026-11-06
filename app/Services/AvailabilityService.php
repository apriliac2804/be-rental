<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Rental;
use Illuminate\Support\Carbon;

/**
 * AvailabilityService
 * -------------------
 * Menangani semua logika pengecekan ketersediaan produk pada rentang tanggal tertentu.
 *
 * Algoritma Overlap Tanggal:
 *   Dua rentang waktu [A, B] dan [C, D] OVERLAP jika: A <= D AND C <= B
 *
 *   Contoh:
 *     Rental existing : 10 Nov – 15 Nov
 *     Request baru    : 13 Nov – 18 Nov  → OVERLAP (bentrok)
 *     Request baru    : 16 Nov – 20 Nov  → NO OVERLAP (aman)
 *     Request baru    :  5 Nov –  9 Nov  → NO OVERLAP (aman)
 */
class AvailabilityService
{
    /**
     * Status rental yang dianggap "aktif" / mengurangi stok.
     * 'cancelled' dan 'completed' tidak mengurangi stok.
     */
    const ACTIVE_STATUSES = [
        Rental::STATUS_PENDING,
        Rental::STATUS_CONFIRMED,
        Rental::STATUS_ACTIVE,
    ];

    /**
     * Hitung stok yang sudah dibooking pada rentang tanggal tertentu
     * untuk satu produk.
     *
     * @param  int    $productId
     * @param  Carbon $startDate
     * @param  Carbon $endDate
     * @param  int|null $excludeRentalId  Abaikan rental tertentu (untuk update/edit)
     * @return int    Jumlah unit yang sedang dibooking
     */
    public function getBookedQuantity(
        int $productId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeRentalId = null
    ): int {
        /*
         * Query:
         *   Cari semua rental_items dengan product_id = $productId
         *   yang rental-nya berstatus aktif (bukan cancelled/completed)
         *   dan tanggal rentalnya OVERLAP dengan [startDate, endDate].
         *
         *   Overlap condition (SQL):
         *     rentals.start_date <= $endDate
         *     AND rentals.end_date >= $startDate
         */
        $query = \DB::table('rental_items')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->where('rental_items.product_id', $productId)
            ->whereIn('rentals.status', self::ACTIVE_STATUSES)
            // Kondisi overlap tanggal
            ->where('rentals.start_date', '<=', $endDate->toDateString())
            ->where('rentals.end_date',   '>=', $startDate->toDateString());

        // Abaikan rental tertentu (berguna saat update rental yang sudah ada)
        if ($excludeRentalId !== null) {
            $query->where('rentals.id', '!=', $excludeRentalId);
        }

        return (int) $query->sum('rental_items.quantity');
    }

    /**
     * Hitung stok yang benar-benar tersedia untuk disewa
     * pada rentang tanggal tertentu.
     *
     * @return int  Stok tersedia (bisa 0 jika tidak ada)
     */
    public function getAvailableStock(
        Product $product,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeRentalId = null
    ): int {
        $booked = $this->getBookedQuantity(
            $product->id,
            $startDate,
            $endDate,
            $excludeRentalId
        );

        return max(0, $product->stock - $booked);
    }

    /**
     * Cek apakah produk tersedia dengan jumlah tertentu
     * pada rentang tanggal yang diminta.
     *
     * @param  int $requestedQty  Jumlah unit yang ingin disewa
     */
    public function isAvailable(
        Product $product,
        Carbon $startDate,
        Carbon $endDate,
        int $requestedQty = 1,
        ?int $excludeRentalId = null
    ): bool {
        // Produk tidak aktif → langsung tidak tersedia
        if ($product->status !== 'available') {
            return false;
        }

        $availableStock = $this->getAvailableStock(
            $product, $startDate, $endDate, $excludeRentalId
        );

        return $availableStock >= $requestedQty;
    }

    /**
     * Cek ketersediaan banyak produk sekaligus dalam satu query batch.
     * Efisien untuk validasi saat membuat rental dengan banyak item.
     *
     * @param  array $items  [['product_id' => X, 'quantity' => Y], ...]
     * @param  Carbon $startDate
     * @param  Carbon $endDate
     * @return array  ['product_id' => available_stock, ...]
     */
    public function getBatchAvailability(
        array $items,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeRentalId = null
    ): array {
        $productIds = array_column($items, 'product_id');

        // Satu query untuk semua produk sekaligus
        $bookedMap = \DB::table('rental_items')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->whereIn('rental_items.product_id', $productIds)
            ->whereIn('rentals.status', self::ACTIVE_STATUSES)
            ->where('rentals.start_date', '<=', $endDate->toDateString())
            ->where('rentals.end_date',   '>=', $startDate->toDateString())
            ->when($excludeRentalId, fn ($q) => $q->where('rentals.id', '!=', $excludeRentalId))
            ->groupBy('rental_items.product_id')
            ->selectRaw('rental_items.product_id, SUM(rental_items.quantity) as booked_qty')
            ->pluck('booked_qty', 'product_id')
            ->toArray();

        // Ambil data produk sekali jalan
        $products = Product::whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($items as $item) {
            $pid     = $item['product_id'];
            $product = $products->get($pid);

            if (! $product) {
                $result[$pid] = ['available_stock' => 0, 'is_available' => false, 'reason' => 'Produk tidak ditemukan'];
                continue;
            }

            $booked    = (int) ($bookedMap[$pid] ?? 0);
            $available = max(0, $product->stock - $booked);
            $requested = (int) ($item['quantity'] ?? 1);

            $result[$pid] = [
                'product_id'      => $pid,
                'product_name'    => $product->name,
                'stock_total'     => $product->stock,
                'booked_qty'      => $booked,
                'available_stock' => $available,
                'requested_qty'   => $requested,
                'is_available'    => $product->status === 'available' && $available >= $requested,
                'reason'          => match (true) {
                    $product->status !== 'available' => 'Produk tidak aktif.',
                    $available === 0                 => 'Stok habis pada tanggal tersebut.',
                    $available < $requested          => "Stok tersedia hanya {$available} unit.",
                    default                          => 'Tersedia',
                },
            ];
        }

        return $result;
    }
}
