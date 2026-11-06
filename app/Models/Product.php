<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'price_per_day',
        'stock',
        'description',
        'image',
        'status',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'stock'         => 'integer',
    ];

    // ----------------------------------------------------------------
    // Relasi
    // ----------------------------------------------------------------

    /**
     * Produk dimiliki oleh satu kategori.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Produk bisa ada di banyak rental_items.
     */
    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    // ----------------------------------------------------------------
    // Local Scopes
    // ----------------------------------------------------------------

    /**
     * Scope: hanya produk yang tersedia.
     *
     * Penggunaan: Product::available()->get();
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available')->where('stock', '>', 0);
    }

    /**
     * Scope: filter berdasarkan kategori.
     *
     * Penggunaan: Product::byCategory(1)->get();
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }
}
