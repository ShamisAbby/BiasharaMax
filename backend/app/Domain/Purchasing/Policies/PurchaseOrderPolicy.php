<?php

namespace App\Domain\Purchasing\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Purchasing\Models\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('purchase_orders.create');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('purchase_orders.create');
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('purchase_orders.approve');
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('purchase_orders.create');
    }

    public function recordPayment(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->business_id === $purchaseOrder->business_id && $user->hasPermission('finance.supplier-payments.manage');
    }
}
