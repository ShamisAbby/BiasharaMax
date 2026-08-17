<?php

namespace App\Domain\Shared\Concerns;

/**
 * Same purpose as SyncsMoneyMinorColumns, for the Inventory module's
 * `_micros` columns (unit_cost, average_cost — see
 * docs/ADR/0002-money-format-migration.md Section 2): a decimal(14,4)
 * column and its integer micros sibling (source value x 1,000,000) are
 * kept in agreement automatically on save.
 *
 * Deliberately does not use the Money value object — Money is minor-units
 * (x100) + currency, and these columns are a different, currency-agnostic
 * scale used only for weighted-average costing precision, never added to
 * or compared against a Money amount directly. Reusing Money here would
 * misrepresent what these columns are.
 */
trait SyncsMoneyMicroColumns
{
    protected static function bootSyncsMoneyMicroColumns(): void
    {
        static::saving(function ($model) {
            foreach ($model->moneyMicroColumns() as $decimalColumn => $microColumn) {
                $model->syncMoneyMicroColumnPair($decimalColumn, $microColumn);
            }
        });
    }

    /**
     * @return array<string, string> decimal column name => micros column name
     */
    abstract protected function moneyMicroColumns(): array;

    private function syncMoneyMicroColumnPair(string $decimalColumn, string $microColumn): void
    {
        $decimalDirty = $this->isDirty($decimalColumn);
        $microDirty = $this->isDirty($microColumn);

        if ($decimalDirty && ! $microDirty) {
            $value = $this->getAttribute($decimalColumn);
            $this->setAttribute(
                $microColumn,
                $value === null ? null : (int) round(((float) $value) * 1_000_000)
            );

            return;
        }

        if ($microDirty && ! $decimalDirty) {
            $value = $this->getAttribute($microColumn);
            $this->setAttribute(
                $decimalColumn,
                $value === null ? null : number_format(((int) $value) / 1_000_000, 4, '.', '')
            );
        }
    }
}
