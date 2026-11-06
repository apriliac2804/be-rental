<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItem extends Model
{
    protected $fillable = [
        'rental_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // ----------------------------------------------------------------
    // Relasi
    // ----------------------------------------------------------------

    /**
     * Item ini milik satu rental.
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Item ini merujuk ke satu produk (snapshot harga saat transaksi).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ----------------------------------------------------------------
    // Accessor
    // ----------------------------------------------------------------

    /**
     * Subtotal = qty × price_per_day × duration_days.
     * Disimpan langsung di DB saat transaksi sebagai snapshot harga final.
     * Accessor ini hanya sebagai fallback jika kolom belum terisi.
     */
    public function getSubtotalAttribute(): float
    {
        return (float) ($this->attributes['subtotal'] ?? ($this->quantity * $this->price));
    }
}
