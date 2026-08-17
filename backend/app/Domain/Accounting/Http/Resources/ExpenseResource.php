<?php

namespace App\Domain\Accounting\Http\Resources;

use App\Domain\Accounting\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date?->toDateString(),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'receipt_path' => $this->receipt_path,
            'is_recurring' => $this->is_recurring,
            'recurrence_frequency' => $this->recurrence_frequency,
            'next_recurrence_date' => $this->next_recurrence_date?->toDateString(),
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ] : null),
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];
    }
}
