<?php

namespace App\Domain\Sales\Http\Requests;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
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
            'customer_id' => ['nullable', 'uuid', Rule::exists(Customer::class, 'id')->where('business_id', $businessId)],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists(Product::class, 'id')->where('business_id', $businessId)],
            'items.*.product_variant_id' => ['nullable', 'uuid'],
            'items.*.product_batch_id' => ['nullable', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],

            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'payments' => ['nullable', 'array'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method' => ['required', 'string', 'in:cash,mobile_money,card,bank_transfer'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
