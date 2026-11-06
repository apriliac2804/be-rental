<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\CheckAvailabilityRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {}

    // =========================================================================
    // GET /api/v1/products
    // =========================================================================
    /**
     * Tampilkan daftar produk dengan filter & pencarian.
     *
     * Query Parameters:
     *   ?search=tenda         → cari nama produk (LIKE)
     *   ?category_id=2        → filter berdasarkan kategori
     *   ?status=available     → filter status ('available' | 'unavailable')
     *   ?min_price=50000      → harga per hari minimum
     *   ?max_price=500000     → harga per hari maksimum
     *   ?sort=price_asc       → urutan: price_asc | price_desc | name_asc | newest
     *   ?per_page=12          → jumlah item per halaman (default 12, maks 50)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 12), 50);

        $products = Product::query()
            ->with('category')                       // eager load category (N+1 prevention)
            ->withCount([                            // hitung jumlah total rental
                'rentalItems as total_rented',
            ])
            // ── Filter: Pencarian nama ──
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('name', 'like', '%' . $request->search . '%')
            )
            // ── Filter: Kategori ──
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->where('category_id', (int) $request->category_id)
            )
            // ── Filter: Status ──
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->status)
            )
            // ── Filter: Rentang Harga ──
            ->when(
                $request->filled('min_price'),
                fn ($q) => $q->where('price_per_day', '>=', (float) $request->min_price)
            )
            ->when(
                $request->filled('max_price'),
                fn ($q) => $q->where('price_per_day', '<=', (float) $request->max_price)
            )
            // ── Sorting ──
            ->when(
                $request->filled('sort'),
                function ($q) use ($request) {
                    match ($request->sort) {
                        'price_asc'  => $q->orderBy('price_per_day', 'asc'),
                        'price_desc' => $q->orderBy('price_per_day', 'desc'),
                        'name_asc'   => $q->orderBy('name', 'asc'),
                        'newest'     => $q->latest(),
                        default      => $q->latest(),
                    };
                },
                fn ($q) => $q->latest()              // default: terbaru
            )
            ->paginate($perPage);

        return $this->successResponse(
            data: ProductResource::collection($products),
            message: 'Daftar produk berhasil diambil.',
            meta: [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ],
        );
    }

    // =========================================================================
    // GET /api/v1/products/{product:slug}
    // =========================================================================
    /**
     * Tampilkan detail satu produk berdasarkan slug.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('category');

        return $this->successResponse(
            data: new ProductResource($product),
            message: 'Detail produk berhasil diambil.',
        );
    }

    // =========================================================================
    // GET /api/v1/products/{product:slug}/availability
    // =========================================================================
    /**
     * Cek ketersediaan stok produk pada rentang tanggal tertentu.
     *
     * Query Parameters (wajib):
     *   ?start_date=2026-11-10
     *   ?end_date=2026-11-15
     *   ?quantity=2            → cek untuk jumlah tertentu (default 1)
     *
     * Response mencakup:
     *   - available_stock   : stok yang tersedia pada tanggal tersebut
     *   - booked_qty        : jumlah yang sudah dibooking pihak lain
     *   - is_available      : boolean, apakah bisa disewa sejumlah qty
     *   - duration_days     : lama sewa dalam hari
     *   - estimated_price   : estimasi biaya total
     *   - booked_dates      : daftar tanggal yang sudah ada booking (info)
     */
    public function checkAvailability(
        CheckAvailabilityRequest $request,
        Product $product
    ): JsonResponse {
        $startDate     = Carbon::parse($request->start_date);
        $endDate       = Carbon::parse($request->end_date);
        $requestedQty  = (int) $request->input('quantity', 1);
        $durationDays  = $startDate->diffInDays($endDate);

        // Hitung stok yang sudah dibooking pada rentang ini
        $bookedQty     = $this->availabilityService->getBookedQuantity(
            $product->id, $startDate, $endDate
        );

        $availableStock = max(0, $product->stock - $bookedQty);
        $isAvailable    = $product->status === 'available'
                          && $availableStock >= $requestedQty;

        // Estimasi total harga
        $estimatedPrice = $durationDays * $product->price_per_day * $requestedQty;

        // Ambil daftar tanggal booking yang overlap (info tambahan untuk UI kalender)
        $bookedPeriods = $this->getBookedPeriods($product->id);

        return $this->successResponse(
            data: [
                'product'         => new ProductResource($product->load('category')),
                'check_period'    => [
                    'start_date'    => $startDate->toDateString(),
                    'end_date'      => $endDate->toDateString(),
                    'duration_days' => $durationDays,
                ],
                'availability'    => [
                    'stock_total'     => $product->stock,
                    'booked_qty'      => $bookedQty,
                    'available_stock' => $availableStock,
                    'requested_qty'   => $requestedQty,
                    'is_available'    => $isAvailable,
                    'reason'          => $this->getUnavailableReason(
                        $product, $isAvailable, $availableStock, $requestedQty
                    ),
                ],
                'pricing'         => [
                    'price_per_day'   => (float) $product->price_per_day,
                    'duration_days'   => $durationDays,
                    'quantity'        => $requestedQty,
                    'estimated_total' => (float) $estimatedPrice,
                ],
                'booked_periods'  => $bookedPeriods,
            ],
            message: $isAvailable
                ? "Produk tersedia untuk disewa {$requestedQty} unit selama {$durationDays} hari."
                : 'Produk tidak tersedia pada rentang tanggal tersebut.',
        );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Kembalikan pesan alasan produk tidak tersedia.
     */
    private function getUnavailableReason(
        Product $product,
        bool $isAvailable,
        int $availableStock,
        int $requestedQty
    ): string {
        if ($isAvailable) {
            return 'Tersedia';
        }

        return match (true) {
            $product->status !== 'available' => 'Produk sedang tidak aktif / tidak dipersewakan.',
            $availableStock === 0            => 'Semua unit sedang disewa pada tanggal tersebut.',
            $availableStock < $requestedQty  => "Stok tersedia hanya {$availableStock} unit, Anda meminta {$requestedQty} unit.",
            default                          => 'Tidak tersedia.',
        };
    }

    /**
     * Ambil semua periode booking aktif untuk suatu produk
     * (berguna untuk UI kalender — highlight tanggal yang tidak bisa dipilih).
     *
     * @return array  [['start_date' => ..., 'end_date' => ..., 'booked_qty' => ...], ...]
     */
    private function getBookedPeriods(int $productId): array
    {
        return \DB::table('rental_items')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->where('rental_items.product_id', $productId)
            ->whereIn('rentals.status', AvailabilityService::ACTIVE_STATUSES)
            ->where('rentals.end_date', '>=', now()->toDateString())  // hanya yang belum selesai
            ->orderBy('rentals.start_date')
            ->selectRaw('
                rentals.start_date,
                rentals.end_date,
                SUM(rental_items.quantity) as booked_qty
            ')
            ->groupBy('rentals.start_date', 'rentals.end_date')
            ->get()
            ->map(fn ($row) => [
                'start_date' => $row->start_date,
                'end_date'   => $row->end_date,
                'booked_qty' => (int) $row->booked_qty,
            ])
            ->toArray();
    }
}
