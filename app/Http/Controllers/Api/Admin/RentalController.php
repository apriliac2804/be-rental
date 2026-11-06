<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRentalStatusRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Services\RentalStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function __construct(
        private readonly RentalStatusService $statusService
    ) {}

    // =========================================================================
    // GET /api/v1/admin/rentals
    // Semua pesanan dari semua customer — khusus admin
    // =========================================================================
    /**
     * Query Parameters opsional:
     *   ?status=pending       → filter status
     *   ?search=INV-2026      → cari invoice code atau nama user
     *   ?per_page=15          → jumlah per halaman (default 15, maks 50)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 50);

        $rentals = Rental::query()
            ->with('user')                           // eager load user
            ->withCount('rentalItems')               // hitung item (N+1 safe)
            // ── Filter status ──
            ->when(
                $request->filled('status'),
                fn ($q) => $q->withStatus($request->status)
            )
            // ── Pencarian invoice code atau nama/email customer ──
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(function ($sub) use ($request) {
                    $sub->where('invoice_code', 'like', '%' . $request->search . '%')
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('email', 'like', '%' . $request->search . '%')
                        );
                })
            )
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            data: RentalResource::collection($rentals),
            message: 'Daftar semua pesanan berhasil diambil.',
            meta: [
                'current_page' => $rentals->currentPage(),
                'last_page'    => $rentals->lastPage(),
                'per_page'     => $rentals->perPage(),
                'total'        => $rentals->total(),
                'summary'      => $this->getStatusSummary(),
            ],
        );
    }

    // =========================================================================
    // GET /api/v1/admin/rentals/{rental}
    // Detail satu pesanan — bisa milik siapapun
    // =========================================================================
    public function show(Rental $rental): JsonResponse
    {
        $rental->load(['rentalItems.product.category', 'user']);

        return $this->successResponse(
            data: new RentalResource($rental),
            message: 'Detail pesanan berhasil diambil.',
        );
    }

    // =========================================================================
    // PUT /api/v1/admin/rentals/{rental}/status
    // Update status pesanan sesuai alur operasional
    // =========================================================================
    /**
     * Alur status yang diizinkan:
     *   pending   → confirmed | cancelled
     *   confirmed → active    | cancelled
     *   active    → completed | cancelled
     *   completed → ✗ (final)
     *   cancelled → ✗ (final)
     *
     * Body JSON:
     * {
     *   "status": "confirmed",
     *   "notes": "Catatan opsional untuk customer" (belum diimplementasikan)
     * }
     */
    public function updateStatus(
        UpdateRentalStatusRequest $request,
        Rental $rental
    ): JsonResponse {
        try {
            $updatedRental = $this->statusService->transition(
                rental:    $rental,
                newStatus: $request->status,
            );

            $statusLabel = (new RentalResource($updatedRental))->resolve()['status_label'];

            return $this->successResponse(
                data: new RentalResource($updatedRental),
                message: "Status pesanan {$rental->invoice_code} berhasil diubah menjadi: {$statusLabel}",
            );
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse(
                message: 'Gagal mengubah status pesanan. Silakan coba lagi.',
                statusCode: 500,
            );
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Hitung ringkasan jumlah pesanan per status
     * untuk ditampilkan di dashboard admin.
     *
     * @return array<string, int>
     */
    private function getStatusSummary(): array
    {
        $counts = Rental::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $allStatuses = [
            Rental::STATUS_PENDING,
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_COMPLETED,
            Rental::STATUS_CANCELLED,
        ];

        $summary = [];
        foreach ($allStatuses as $status) {
            $summary[$status] = $counts[$status] ?? 0;
        }
        $summary['total'] = array_sum($counts);

        return $summary;
    }
}
