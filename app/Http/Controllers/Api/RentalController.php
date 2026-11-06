<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RentalController extends Controller
{
    public function __construct(
        private readonly RentalService $rentalService
    ) {}

    // =========================================================================
    // GET /api/v1/rentals
    // Riwayat pesanan milik user yang sedang login
    // =========================================================================
    /**
     * Query Parameters opsional:
     *   ?status=pending          → filter status
     *   ?per_page=10             → jumlah per halaman (default 10, maks 50)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);

        $rentals = Rental::query()
            ->forUser($request->user()->id)                 // scope: milik user ini saja
            ->withCount('rentalItems')                      // hitung jumlah item (N+1 safe)
            ->when(
                $request->filled('status'),
                fn ($q) => $q->withStatus($request->status)
            )
            ->latest()                                       // terbaru di atas
            ->paginate($perPage);

        return $this->successResponse(
            data: RentalResource::collection($rentals),
            message: 'Riwayat pesanan berhasil diambil.',
            meta: [
                'current_page' => $rentals->currentPage(),
                'last_page'    => $rentals->lastPage(),
                'per_page'     => $rentals->perPage(),
                'total'        => $rentals->total(),
            ],
        );
    }

    // =========================================================================
    // POST /api/v1/rentals
    // Checkout / Buat pesanan baru
    // =========================================================================
    /**
     * Body JSON:
     * {
     *   "start_date": "2026-11-10",
     *   "end_date":   "2026-11-15",
     *   "items": [
     *     { "product_id": 1, "quantity": 2 },
     *     { "product_id": 5, "quantity": 1 }
     *   ],
     *   "notes": "Tolong siapkan tali tambahan." (opsional)
     * }
     */
    public function store(StoreRentalRequest $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);

        $result = $this->rentalService->checkout(
            user:      $request->user(),
            startDate: $startDate,
            endDate:   $endDate,
            items:     $request->items,
        );

        // Ada produk yang tidak tersedia → kembalikan error 409 Conflict
        if (! empty($result['errors'])) {
            return $this->errorResponse(
                message: 'Beberapa produk tidak tersedia pada tanggal yang dipilih.',
                statusCode: 409,
                errors: $result['errors'],
            );
        }

        return $this->createdResponse(
            data: new RentalResource($result['rental']),
            message: 'Pesanan berhasil dibuat! Silakan tunggu konfirmasi dari admin.',
        );
    }

    // =========================================================================
    // GET /api/v1/rentals/{rental}
    // Detail pesanan — hanya milik user yang login
    // =========================================================================
    public function show(Request $request, Rental $rental): JsonResponse
    {
        // Pastikan rental ini milik user yang sedang login
        if ($rental->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Anda tidak memiliki akses ke pesanan ini.');
        }

        // Eager load relasi lengkap untuk detail view
        $rental->load([
            'rentalItems.product.category',
        ]);

        return $this->successResponse(
            data: new RentalResource($rental),
            message: 'Detail pesanan berhasil diambil.',
        );
    }
}
