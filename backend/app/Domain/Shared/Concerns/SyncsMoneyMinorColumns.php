<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Keeps a model's legacy `decimal(x,2)` money columns and their new
 * integer `_minor` siblings in agreement automatically, for the
 * dual-write phase of the money-format migration described in
 * docs/ADR/0002-money-format-migration.md.
 *
 * Rewriting every call site that creates/updates one of these models
 * (controllers, seeders, console commands, tests, and the services that
 * *have* already been ported to the Money value object) to always set
 * both columns is exactly the kind of thing that's easy to miss once and
 * silently wrong forever after — a caller that only knows about the old
 * decimal column would leave `_minor` at its default of 0, and any
 * Money-aware code reading `_minor` (e.g. Customer::creditLimitMoney())
 * would then be silently wrong rather than erroring loudly.
 *
 * Using classes list which decimal/minor column pairs to keep in sync via
 * moneyMinorColumns(). On save:
 *   - only the decimal column changed -> derive `_minor` from it (legacy
 *     write path, e.g. an un-migrated controller or a direct ::create()
 *     in an older test).
 *   - only `_minor` changed -> derive the decimal column from it (a
 *     Money-aware write path that only bothered to set the new column).
 *   - both changed -> trust the caller and touch neither (this is the
 *     normal case for services that have already been ported to compute
 *     both explicitly via Money, e.g. SalePaymentService, SaleReturnService).
 *   - neither changed -> nothing to do.
 */
trait SyncsMoneyMinorColumns
{
    protected static function bootSyncsMoneyMinorColumns(): void
    {
        static::saving(function ($model) {
            foreach ($model->moneyMinorColumns() as $decimalColumn => $minorColumn) {
                $model->syncMoneyMinorColumnPair($decimalColumn, $minorColumn);
            }
        });
    }

    /**
     * @return array<string, string> decimal column name => minor column name
     */
    abstract protected function moneyMinorColumns(): array;

    /**
     * Override if a model has a cheaper/more direct way to resolve its
     * business's currency than the default `business` relation.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->business?->currency ?? 'TZS';
    }

    private function syncMoneyMinorColumnPair(string $decimalColumn, string $minorColumn): void
    {
        $decimalDirty = $this->isDirty($decimalColumn);
        $minorDirty = $this->isDirty($minorColumn);

        if ($decimalDirty && ! $minorDirty) {
            $value = $this->getAttribute($decimalColumn);
            $this->setAttribute(
                $minorColumn,
                $value === null ? null : Money::fromDecimal((string) $value, $this->moneyMinorCurrency())->minorUnits()
            );

            return;
        }

        if ($minorDirty && ! $decimalDirty) {
            $value = $this->getAttribute($minorColumn);
            $this->setAttribute(
                $decimalColumn,
                $value === null ? null : Money::fromMinorUnits((int) $value, $this->moneyMinorCurrency())->toDecimalString()
            );
        }
    }
}
