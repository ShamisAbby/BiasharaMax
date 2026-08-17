<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\StockTransfer;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_transfers.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->business_id === $transfer->business_id && $user->hasPermission('stock_transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_transfers.create');
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return $user->business_id === $transfer->business_id && $user->hasPermission('stock_transfers.dispatch');
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $user->business_id === $transfer->business_id && $user->hasPermission('stock_transfers.receive');
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $user->business_id === $transfer->business_id && $user->hasPermission('stock_transfers.cancel');
    }
}
