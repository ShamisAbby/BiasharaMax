<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Events\IncomeRecorded;
use App\Domain\Accounting\Models\Income;

class IncomeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Income
    {
        $income = Income::create($data);

        IncomeRecorded::dispatch($income);

        return $income;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Income $income, array $data): Income
    {
        $income->update($data);

        return $income->refresh();
    }

    public function delete(Income $income): void
    {
        $income->delete();
    }
}
