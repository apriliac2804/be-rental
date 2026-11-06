<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RentalStatusService
 * -------------------
 * Menangani seluruh logika perubahan status rental, termasuk:
 *  - Validasi transisi status
 *  - Pengembalian stok saat completed / cancelled
 *
 * Catatan Stok:
 *   Aplikasi ini menggunakan "virtual stock" — stok di tabel products
 *   adalah stok fisik total. Ketersediaan dihitung real-time dari
 *   rental_items yang overlap tanggal (via AvailabilityService).
 *
 *   Sehingga TIDAK ada pengurangan/penambahan stok saat status berubah.
 *   Stok "terkunci" otomatis melalui query availability.
 *
 *   NAMUN, jika bisnis ingin stok fisik dikurangi saat active dan
 *   dikembalikan saat completed/cancelled — metode adjustStock() tersedia.
 */
class RentalStatusService
{
    /**
     * Proses perubahan status rental dalam satu DB Transaction.
     *
     * @param  Rental $rental
     * @param  string $newStatus
     * @return Rental  Rental yang sudah diupdate (dengan relasi ter-load)
     * @throws \Throwable
     */
    public function transition(Rental $rental, string $newStatus): Rental
    {
        return DB::transaction(function () use ($rental, $newStatus) {
            $oldStatus = $rental->status;

            // Update status
            $rental->update(['status' => $newStatus]);

            // Side-effect berdasarkan status tujuan
            match ($newStatus) {
                Rental::STATUS_ACTIVE    => $this->onActive($rental),
                Rental::STATUS_COMPLETED => $this->onCompleted($rental),
                Rental::STATUS_CANCELLED => $this->onCancelled($rental, $oldStatus),
                default                  => null,   // confirmed: tidak ada side-effect
            };

            Log::info('Rental status changed', [
                'rental_id'    => $rental->id,
                'invoice_code' => $rental->invoice_code,
                'old_status'   => $oldStatus,
                'new_status'   => $newStatus,
            ]);

            return $rental->fresh(['rentalItems.product.category', 'user']);
        });
    }

    // =========================================================================
    // Side-Effect Handlers
    // =========================================================================

    /**
     * Status → active: Barang diserahkan ke customer.
     *
     * Jika bisnis menggunakan stok fisik (bukan virtual):
     *   Kurangi stok produk sejumlah quantity yang disewa.
     */
    private function onActive(Rental $rental): void
    {
        // Untuk skenario "virtual stock" (default): tidak ada aksi DB tambahan.
        // Availability sudah dihitung real-time dari rental_items yang overlap.

        // ── Opsional: jika ingin mengurangi stok fisik ──────────────────────
        // $this->decrementStock($rental);
    }

    /**
     * Status → completed: Barang dikembalikan oleh customer.
     *
     * Karena menggunakan "virtual stock", rental yang completed
     * tidak dihitung lagi dalam availability query (status not in active statuses).
     * Stok "kembali" otomatis karena AvailabilityService::ACTIVE_STATUSES
     * tidak memasukkan 'completed'.
     */
    private function onCompleted(Rental $rental): void
    {
        // Virtual stock: tidak ada aksi tambahan — status 'completed' otomatis
        // dikeluarkan dari query overlap di AvailabilityService.

        // ── Opsional: jika menggunakan stok fisik ────────────────────────────
        // $this->incrementStock($rental);
    }

    /**
     * Status → cancelled: Pesanan dibatalkan.
     *
     * Sama seperti completed — stok kembali tersedia secara otomatis
     * karena 'cancelled' tidak termasuk ACTIVE_STATUSES.
     */
    private function onCancelled(Rental $rental, string $oldStatus): void
    {
        // Virtual stock: tidak ada aksi tambahan.

        // ── Opsional: jika menggunakan stok fisik dan sudah active ───────────
        // if ($oldStatus === Rental::STATUS_ACTIVE) {
        //     $this->incrementStock($rental);
        // }
    }

    // =========================================================================
    // Stok Fisik — Diaktifkan jika berpindah ke mode "physical stock"
    // =========================================================================

    /**
     * Kurangi stok fisik produk saat barang diserahkan (→ active).
     *
     * Menggunakan DB::table dengan lockForUpdate() untuk mencegah
     * race condition saat concurrent requests.
     */
    private function decrementStock(Rental $rental): void
    {
        $rental->load('rentalItems');

        foreach ($rental->rentalItems as $item) {
            DB::table('products')
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->decrement('stock', $item->quantity);
        }
    }

    /**
     * Kembalikan stok fisik saat barang dikembalikan / dibatalkan.
     */
    private function incrementStock(Rental $rental): void
    {
        $rental->load('rentalItems');

        foreach ($rental->rentalItems as $item) {
            DB::table('products')
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->increment('stock', $item->quantity);
        }
    }

    // =========================================================================
    // Helper
    // =========================================================================

    /**
     * Kembalikan map transisi status yang diizinkan.
     *
     * @return array<string, string[]>
     */
    public static function allowedTransitions(): array
    {
        return [
            Rental::STATUS_PENDING   => [Rental::STATUS_CONFIRMED, Rental::STATUS_CANCELLED],
            Rental::STATUS_CONFIRMED => [Rental::STATUS_ACTIVE,    Rental::STATUS_CANCELLED],
            Rental::STATUS_ACTIVE    => [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED],
        ];
    }
}
