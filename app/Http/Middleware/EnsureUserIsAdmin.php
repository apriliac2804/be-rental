<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Pastikan user yang terautentikasi memiliki role 'admin'.
     *
     * Middleware ini HARUS dipakai SETELAH auth:sanctum karena
     * membutuhkan user() yang sudah ter-resolve.
     *
     * Urutan stack: auth:sanctum → admin
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Seharusnya tidak terjadi jika auth:sanctum sudah di-stack sebelumnya,
        // tapi sebagai defense-in-depth:
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        if (! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Endpoint ini hanya untuk administrator.',
            ], 403);
        }

        return $next($request);
    }
}
