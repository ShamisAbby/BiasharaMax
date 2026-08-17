<?php

namespace App\Domain\Sales\Http\Requests;

use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleReturnStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SaleReturn::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $saleId = $this->route('sale')?->id;

        return [
            'reason' => ['required', 'string', 'in:damaged,wrong_item,expired,defective,changed_mind,other'],
            'refund_method' => ['nullable', 'string', 'in:cash,bank_transfer,mobile_money,card,store_credit'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'uuid', Rule::exists(SaleItem::class, 'id')->where('sale_id', $saleId)],
            'items.*.quantity_returned' => ['required', 'numeric', 'min:0.001'],
            'items.*.condition' => ['nullable', 'string', 'in:good,damaged,expired'],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
