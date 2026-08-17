<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockTransfer::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'from_warehouse_id' => ['required', 'uuid', 'different:to_warehouse_id', Rule::exists(Warehouse::class, 'id')->where('business_id', $businessId)],
            'to_warehouse_id' => ['required', 'uuid', Rule::exists(Warehouse::class, 'id')->where('business_id', $businessId)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists(Product::class, 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'uuid', Rule::exists(ProductVariant::class, 'id')->where('business_id', $businessId)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
