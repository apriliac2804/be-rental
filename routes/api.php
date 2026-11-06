<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Rental Alat Outdoor & Camping  |  Versi: v1
|--------------------------------------------------------------------------
|
| Layer akses:
|  PUBLIC  → tanpa token
|  PRIVATE → auth:sanctum (Bearer Token)
|  ADMIN   → auth:sanctum + admin (role = 'admin')
|
*/

Route::prefix('v1')->group(function () {

    // ====================================================================
    // PUBLIC — Auth
    // ====================================================================
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login',    [AuthController::class, 'login'])->name('login');
    });

    // ====================================================================
    // PUBLIC — Katalog (read-only)
    // ====================================================================
    Route::get('/categories',                           [\App\Http\Controllers\Api\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category:slug}',           [\App\Http\Controllers\Api\CategoryController::class, 'show'])->name('categories.show');

    Route::get('/products',                             [\App\Http\Controllers\Api\ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product:slug}/availability', [\App\Http\Controllers\Api\ProductController::class, 'checkAvailability'])->name('products.availability');
    Route::get('/products/{product:slug}',              [\App\Http\Controllers\Api\ProductController::class, 'show'])->name('products.show');

    // ====================================================================
    // PRIVATE — auth:sanctum
    // ====================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me',      [AuthController::class, 'me'])->name('me');
        });

        // Customer Rentals
        Route::apiResource('rentals', \App\Http\Controllers\Api\RentalController::class)
             ->only(['index', 'store', 'show']);

        // ================================================================
        // ADMIN — auth:sanctum + admin (role = 'admin')
        // ================================================================
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {

            // ── Rental Management ────────────────────────────────────────
            Route::prefix('rentals')->name('rentals.')->group(function () {
                Route::get('/',                         [\App\Http\Controllers\Api\Admin\RentalController::class, 'index'])->name('index');
                Route::get('/{rental}',                 [\App\Http\Controllers\Api\Admin\RentalController::class, 'show'])->name('show');
                Route::put('/{rental}/status',          [\App\Http\Controllers\Api\Admin\RentalController::class, 'updateStatus'])->name('update-status');
            });

            // ── Category CRUD ────────────────────────────────────────────
            Route::apiResource('categories', \App\Http\Controllers\Api\Admin\CategoryController::class)
                 ->except(['index', 'show']);

            // ── Product CRUD ─────────────────────────────────────────────
            Route::apiResource('products', \App\Http\Controllers\Api\Admin\ProductController::class)
                 ->except(['index', 'show']);

        }); // end admin

    }); // end auth:sanctum

}); // end v1
