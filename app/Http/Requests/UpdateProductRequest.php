<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'available_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'flash_sale_start' => ['sometimes', 'required', 'date'],
            'flash_sale_end' => [
                'sometimes',
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($product): void {
                    $start = $this->input('flash_sale_start', $product->flash_sale_start);

                    if ($start !== null && strtotime((string) $value) <= strtotime((string) $start)) {
                        $fail('The flash sale end must be after flash sale start.');
                    }
                },
            ],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
