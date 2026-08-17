<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Events\ExpenseMarkedPaid;
use App\Domain\Accounting\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $receipt = null): Expense
    {
        if ($receipt) {
            $data['receipt_path'] = Storage::url($receipt->store('expenses/receipts', 'public'));
        }

        return Expense::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data, ?UploadedFile $receipt = null): Expense
    {
        if ($receipt) {
            $data['receipt_path'] = Storage::url($receipt->store('expenses/receipts', 'public'));
        }

        $expense->update($data);

        return $expense->refresh();
    }

    public function approve(Expense $expense, string $approvedBy): Expense
    {
        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $expense->refresh();
    }

    public function reject(Expense $expense, string $rejectedBy, string $reason): Expense
    {
        $expense->update([
            'status' => Expense::STATUS_REJECTED,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $expense->refresh();
    }

    public function markPaid(Expense $expense): Expense
    {
        $expense->update(['status' => Expense::STATUS_PAID]);
        $expense->refresh();

        ExpenseMarkedPaid::dispatch($expense);

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
