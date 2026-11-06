<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($categoryId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($categoryId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama kategori sudah digunakan.',
            'slug.unique' => 'Slug sudah digunakan.',
            'slug.regex'  => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors(),
        ], 422));
    }
}
