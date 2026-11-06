<?php

namespace App\Http\Requests\Rental;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth sudah dijaga middleware auth:sanctum di route
    }

    public function rules(): array
    {
        return [
            // ── Tanggal ──────────────────────────────────────────────────
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after:start_date',
            ],

            // ── Items (array produk yang disewa) ─────────────────────────
            'items' => [
                'required',
                'array',
                'min:1',
                'max:10',           // maks 10 jenis produk per transaksi
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],

            // ── Catatan opsional ─────────────────────────────────────────
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'          => 'Tanggal mulai wajib diisi.',
            'start_date.date_format'       => 'Format tanggal mulai harus YYYY-MM-DD.',
            'start_date.after_or_equal'    => 'Tanggal mulai tidak boleh di masa lalu.',
            'end_date.required'            => 'Tanggal selesai wajib diisi.',
            'end_date.date_format'         => 'Format tanggal selesai harus YYYY-MM-DD.',
            'end_date.after'               => 'Tanggal selesai harus setelah tanggal mulai.',
            'items.required'               => 'Daftar produk wajib diisi.',
            'items.array'                  => 'Format items harus berupa array.',
            'items.min'                    => 'Minimal harus ada 1 produk yang dipesan.',
            'items.max'                    => 'Maksimal 10 jenis produk per transaksi.',
            'items.*.product_id.required'  => 'Product ID wajib diisi pada setiap item.',
            'items.*.product_id.exists'    => 'Produk dengan ID :input tidak ditemukan.',
            'items.*.quantity.required'    => 'Quantity wajib diisi pada setiap item.',
            'items.*.quantity.min'         => 'Quantity minimal 1 unit.',
            'items.*.quantity.max'         => 'Quantity maksimal 20 unit per produk.',
            'notes.max'                    => 'Catatan maksimal 500 karakter.',
        ];
    }

    /**
     * Tambahan: pastikan tidak ada product_id duplikat dalam satu request.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $items      = $this->input('items', []);
            $productIds = array_column($items, 'product_id');
            $unique     = array_unique($productIds);

            if (count($productIds) !== count($unique)) {
                $v->errors()->add('items', 'Terdapat produk duplikat dalam daftar pesanan. Gunakan satu baris per produk.');
            }
        });
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
