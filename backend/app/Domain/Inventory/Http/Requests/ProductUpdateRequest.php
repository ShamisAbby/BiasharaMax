<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Collection;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Inventory\Models\Unit;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;
        $product = $this->route('product');

        return [
            'category_id' => ['nullable', 'uuid', Rule::exists(Category::class, 'id')->where('business_id', $businessId)],
            'brand_id' => ['nullable', 'uuid', Rule::exists(Brand::class, 'id')->where('business_id', $businessId)],
            'unit_id' => ['nullable', 'uuid', Rule::exists(Unit::class, 'id')->where('business_id', $businessId)],
            'default_supplier_id' => ['nullable', 'uuid', Rule::exists(Supplier::class, 'id')->where('business_id', $businessId)],

            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products')->where('business_id', $businessId)->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products')->where('business_id', $businessId)->ignore($product)],
            'custom_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],

            'product_type' => ['required', 'string', 'in:simple,variable,service'],
            'track_stock' => ['boolean'],
            'has_expiry' => ['boolean'],
            'has_batch' => ['boolean'],
            'has_serial' => ['boolean'],

            'cost_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],

            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:10'],
            'dimensions' => ['nullable', 'array'],

            'status' => ['required', 'string', 'in:active,inactive,archived'],
            'visibility' => ['required', 'string', 'in:visible,hidden'],

            'tag_ids' => ['array'],
            'tag_ids.*' => ['uuid', Rule::exists(Tag::class, 'id')->where('business_id', $businessId)],
            'collection_ids' => ['array'],
            'collection_ids.*' => ['uuid', Rule::exists(Collection::class, 'id')->where('business_id', $businessId)],
            'supplier_ids' => ['array'],
            'supplier_ids.*' => ['uuid', Rule::exists(Supplier::class, 'id')->where('business_id', $businessId)],

            'variants' => ['array'],
            'variants.*.id' => ['nullable', 'uuid'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
