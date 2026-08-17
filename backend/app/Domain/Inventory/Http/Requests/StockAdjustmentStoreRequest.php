<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use App\Domain\Inventory\Models\StockAdjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'branch_id' => ['required', 'uuid', Rule::exists(Branch::class, 'id')->where('business_id', $businessId)],
            'warehouse_id' => ['required', 'uuid', Rule::exists(Warehouse::class, 'id')->where('business_id', $businessId)],
            'reason' => ['required', 'string', 'in:damage,theft,expiry,correction,count,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists(Product::class, 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'uuid', Rule::exists(ProductVariant::class, 'id')->where('business_id', $businessId)],
            'items.*.direction' => ['required', 'string', 'in:in,out'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'         => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number'      => ['nullable', 'string', 'max:100'],
            'items.*.manufactured_date' => ['nullable', 'date'],
            'items.*.expiry_date'       => ['nullable', 'date', 'after:today'],
        ];
    }
}
