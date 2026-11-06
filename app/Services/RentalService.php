<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentalService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {}

    // =========================================================================
    // GENERATE INVOICE CODE
    // =========================================================================
    /**
     * Generate kode invoice unik dengan format:
     *   INV-YYYYMMDD-XXXXXX  (contoh: INV-20261110-A3F8K2)
     *
     * Dijamin unik karena loop hingga tidak ada duplikat di DB.
     */
    public function generateInvoiceCode(): string
    {
        do {
            $code = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Rental::where('invoice_code', $code)->exists());

        return $code;
    }

    // =========================================================================
    // CHECKOUT — buat transaksi rental baru
    // =========================================================================
    /**
     * Proses checkout:
     *   1. Cek ketersediaan semua produk (batch — satu query)
     *   2. Hitung total harga
     *   3. Simpan ke DB dalam satu transaction
     *
     * @param  User   $user
     * @param  Carbon $startDate
     * @param  Carbon $endDate
     * @param  array  $items       [['product_id' => X, 'quantity' => Y], ...]
     * @return array  ['rental' => Rental, 'errors' => []]
     * @throws \Throwable
     */
    public function checkout(
        User $user,
        Carbon $startDate,
        Carbon $endDate,
        array $items
    ): array {
        $durationDays = $startDate->diffInDays($endDate);

        // ── 1. Pengecekan Ketersediaan Batch ─────────────────────────────────
        $availability = $this->availabilityService->getBatchAvailability(
            $items, $startDate, $endDate
        );

        $unavailable = array_filter($availability, fn ($a) => ! $a['is_available']);

        if (! empty($unavailable)) {
            return [
                'rental' => null,
                'errors' => array_values($unavailable),
            ];
        }

        // ── 2. Ambil data produk & hitung harga ───────────────────────────────
        $productIds   = array_column($items, 'product_id');
        $productsMap  = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lineItems  = [];
        $totalPrice = 0;

        foreach ($items as $item) {
            $product    = $productsMap->get($item['product_id']);
            $qty        = (int) $item['quantity'];
            $unitPrice  = (float) $product->price_per_day;
            $subtotal   = $qty * $unitPrice * $durationDays;

            $lineItems[] = [
                'product'   => $product,
                'quantity'  => $qty,
                'price'     => $unitPrice,      // snapshot harga per hari saat ini
                'subtotal'  => $subtotal,
            ];

            $totalPrice += $subtotal;
        }

        // ── 3. Simpan ke Database (DB Transaction) ────────────────────────────
        $rental = DB::transaction(function () use (
            $user, $startDate, $endDate, $totalPrice, $lineItems
        ) {
            // a) Buat record rental
            $rental = Rental::create([
                'user_id'      => $user->id,
                'invoice_code' => $this->generateInvoiceCode(),
                'start_date'   => $startDate->toDateString(),
                'end_date'     => $endDate->toDateString(),
                'total_price'  => $totalPrice,
                'status'       => Rental::STATUS_PENDING,
            ]);

            // b) Buat semua rental_items sekaligus (bulk insert)
            $rentalItemsData = array_map(fn ($line) => [
                'rental_id'  => $rental->id,
                'product_id' => $line['product']->id,
                'quantity'   => $line['quantity'],
                'price'      => $line['price'],
                'subtotal'   => $line['subtotal'],   // snapshot: qty × price × durasi
                'created_at' => now(),
                'updated_at' => now(),
            ], $lineItems);

            RentalItem::insert($rentalItemsData);

            return $rental;
        });

        // Load relasi untuk response
        $rental->load(['rentalItems.product.category', 'user']);

        return [
            'rental' => $rental,
            'errors' => [],
        ];
    }
}
