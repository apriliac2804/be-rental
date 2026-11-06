<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // =========================================================================
    // POST /api/v1/admin/categories
    // =========================================================================
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $slug = $request->filled('slug')
            ? $request->slug
            : $this->generateUniqueSlug($request->name);

        $category = Category::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return $this->createdResponse(
            data: new CategoryResource($category),
            message: "Kategori '{$category->name}' berhasil dibuat.",
        );
    }

    // =========================================================================
    // PUT /api/v1/admin/categories/{category}
    // =========================================================================
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->only(['name', 'slug']);

        // Regenerate slug jika nama berubah tapi slug tidak disertakan
        if ($request->filled('name') && ! $request->filled('slug')) {
            $data['slug'] = $this->generateUniqueSlug($request->name, $category->id);
        }

        $category->update($data);

        return $this->successResponse(
            data: new CategoryResource($category->fresh()),
            message: "Kategori '{$category->name}' berhasil diperbarui.",
        );
    }

    // =========================================================================
    // DELETE /api/v1/admin/categories/{category}
    // =========================================================================
    public function destroy(Category $category): JsonResponse
    {
        // Cek apakah kategori masih digunakan oleh produk
        $productCount = $category->products()->count();
        if ($productCount > 0) {
            return $this->errorResponse(
                message: "Kategori '{$category->name}' tidak dapat dihapus karena masih memiliki {$productCount} produk.",
                statusCode: 409,
            );
        }

        $name = $category->name;
        $category->delete();

        return $this->successResponse(
            message: "Kategori '{$name}' berhasil dihapus.",
        );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Generate slug unik dari nama.
     * Jika slug sudah ada, tambahkan suffix angka: tenda, tenda-2, tenda-3, dst.
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base  = Str::slug($name);
        $slug  = $base;
        $count = 2;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }
}
