<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\StockAdjustment;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_adjustments.view');
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->business_id === $adjustment->business_id && $user->hasPermission('stock_adjustments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_adjustments.create');
    }

    public function complete(User $user, StockAdjustment $adjustment): bool
    {
        return $user->business_id === $adjustment->business_id && $user->hasPermission('stock_adjustments.complete');
    }

    /**
     * Completed adjustments have already moved stock and written
     * immutable ledger entries — deleting the document afterward would
     * leave the ledger referencing a vanished record, so only drafts can
     * be deleted.
     */
    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $user->business_id === $adjustment->business_id
            && $user->hasPermission('stock_adjustments.delete')
            && ! $adjustment->isCompleted();
    }
}
