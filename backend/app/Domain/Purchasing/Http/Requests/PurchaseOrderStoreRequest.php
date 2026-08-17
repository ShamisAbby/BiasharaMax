<?php

namespace App\Domain\Purchasing\Http\Requests;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists(Branch::class, 'id')->where('business_id', $businessId)],
            'warehouse_id' => ['nullable', 'uuid', Rule::exists(Warehouse::class, 'id')->where('business_id', $businessId)],
            'supplier_id' => ['required', 'uuid', Rule::exists(Supplier::class, 'id')->where('business_id', $businessId)],

            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists(Product::class, 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'uuid'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],

            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ];
    }
}
