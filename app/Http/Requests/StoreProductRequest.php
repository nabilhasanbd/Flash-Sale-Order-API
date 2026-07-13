<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'gt:0'],
            'available_stock' => ['required', 'integer', 'min:0'],
            'flash_sale_start' => ['required', 'date'],
            'flash_sale_end' => ['required', 'date', 'after:flash_sale_start'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
