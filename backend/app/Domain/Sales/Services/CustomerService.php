<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\Customer;

class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->refresh();
    }

    public function deactivate(Customer $customer): Customer
    {
        $customer->update(['is_active' => false]);

        return $customer->refresh();
    }

    public function activate(Customer $customer): Customer
    {
        $customer->update(['is_active' => true]);

        return $customer->refresh();
    }
}
