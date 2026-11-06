<?php

namespace App\Http\Requests\Admin;

use App\Models\Rental;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRentalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sudah dijaga oleh middleware auth:sanctum + admin
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:confirmed,active,completed,cancelled',
            ],
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
            'status.required' => 'Status wajib diisi.',
            'status.in'       => 'Status tidak valid. Pilihan: confirmed, active, completed, cancelled.',
        ];
    }

    /**
     * Validasi tambahan: pastikan transisi status mengikuti alur yang benar.
     *
     * Alur yang diizinkan:
     *   pending   → confirmed | cancelled
     *   confirmed → active    | cancelled
     *   active    → completed | cancelled
     *   completed → (tidak bisa diubah lagi)
     *   cancelled → (tidak bisa diubah lagi)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rental    = $this->route('rental');
            $newStatus = $this->input('status');

            if (! $rental instanceof Rental) {
                return;
            }

            $currentStatus = $rental->status;

            // Status final — tidak bisa diubah sama sekali
            $finalStatuses = [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED];
            if (in_array($currentStatus, $finalStatuses)) {
                $v->errors()->add(
                    'status',
                    "Pesanan dengan status '{$currentStatus}' tidak dapat diubah lagi."
                );
                return;
            }

            // Transisi yang diizinkan
            $allowedTransitions = [
                Rental::STATUS_PENDING   => [Rental::STATUS_CONFIRMED, Rental::STATUS_CANCELLED],
                Rental::STATUS_CONFIRMED => [Rental::STATUS_ACTIVE,    Rental::STATUS_CANCELLED],
                Rental::STATUS_ACTIVE    => [Rental::STATUS_COMPLETED, Rental::STATUS_CANCELLED],
            ];

            $allowed = $allowedTransitions[$currentStatus] ?? [];

            if (! in_array($newStatus, $allowed)) {
                $allowedStr = implode(', ', $allowed);
                $v->errors()->add(
                    'status',
                    "Tidak bisa mengubah status dari '{$currentStatus}' ke '{$newStatus}'. Status yang diizinkan: {$allowedStr}."
                );
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
