<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Product Controller — CRUD produk
 * Implementasi lengkap di Tahap 6.
 */
class ProductController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return $this->successResponse(message: 'Endpoint belum diimplementasikan.', statusCode: 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->successResponse(message: 'Endpoint belum diimplementasikan.', statusCode: 501);
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->successResponse(message: 'Endpoint belum diimplementasikan.', statusCode: 501);
    }
}
