<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_code',
        'start_date',
        'end_date',
        'total_price',
        'status',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'total_price' => 'decimal:2',
    ];

    // ----------------------------------------------------------------
    // Konstanta Status
    // ----------------------------------------------------------------
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_ACTIVE    = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // ----------------------------------------------------------------
    // Relasi
    // ----------------------------------------------------------------

    /**
     * Rental dimiliki oleh satu user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Satu rental memiliki banyak item (produk yang disewa).
     */
    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    // ----------------------------------------------------------------
    // Accessor
    // ----------------------------------------------------------------

    /**
     * Hitung durasi sewa dalam hari.
     */
    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    // ----------------------------------------------------------------
    // Local Scopes
    // ----------------------------------------------------------------

    /**
     * Filter berdasarkan status.
     *
     * Penggunaan: Rental::withStatus('pending')->get();
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Hanya rental milik user tertentu.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
