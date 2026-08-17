<?php

namespace App\Domain\Accounting\Http\Requests;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists(Branch::class, 'id')->where('business_id', $businessId)],
            'expense_category_id' => ['nullable', 'uuid', Rule::exists(ExpenseCategory::class, 'id')->where('business_id', $businessId)],
            'supplier_id' => ['nullable', 'uuid', Rule::exists(Supplier::class, 'id')->where('business_id', $businessId)],
            'employee_id' => ['nullable', 'uuid', Rule::exists(User::class, 'id')->where('business_id', $businessId)],

            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,mobile_money,card,cheque,other'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_frequency' => ['nullable', 'required_if:is_recurring,true', 'string', 'in:daily,weekly,monthly,yearly'],
            'next_recurrence_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
