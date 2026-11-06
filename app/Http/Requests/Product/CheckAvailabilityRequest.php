<?php

namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',     // tidak boleh tanggal lampau
            ],
            'end_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after:start_date',          // end_date harus setelah start_date
            ],
            'quantity' => [
                'sometimes',                 // opsional, default 1
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'         => 'Tanggal mulai wajib diisi.',
            'start_date.date'             => 'Format tanggal mulai tidak valid.',
            'start_date.date_format'      => 'Tanggal mulai harus dalam format YYYY-MM-DD.',
            'start_date.after_or_equal'   => 'Tanggal mulai tidak boleh lebih awal dari hari ini.',
            'end_date.required'           => 'Tanggal selesai wajib diisi.',
            'end_date.date'               => 'Format tanggal selesai tidak valid.',
            'end_date.date_format'        => 'Tanggal selesai harus dalam format YYYY-MM-DD.',
            'end_date.after'              => 'Tanggal selesai harus setelah tanggal mulai.',
            'quantity.integer'            => 'Jumlah harus berupa angka.',
            'quantity.min'                => 'Jumlah minimal 1 unit.',
            'quantity.max'                => 'Jumlah maksimal 100 unit.',
        ];
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
