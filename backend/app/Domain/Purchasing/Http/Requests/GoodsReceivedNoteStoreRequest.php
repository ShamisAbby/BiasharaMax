<?php

namespace App\Domain\Purchasing\Http\Requests;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceivedNoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GoodsReceivedNote::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;
        $purchaseOrderId = $this->route('order')?->id;

        return [
            'branch_id' => ['required', 'uuid', Rule::exists(Branch::class, 'id')->where('business_id', $businessId)],
            'warehouse_id' => ['required', 'uuid', Rule::exists(Warehouse::class, 'id')->where('business_id', $businessId)],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => [
                'required', 'uuid',
                Rule::exists(PurchaseOrderItem::class, 'id')->where('purchase_order_id', $purchaseOrderId),
            ],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_damaged' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufactured_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date', 'after:items.*.manufactured_date'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
