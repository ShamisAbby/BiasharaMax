<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * A monetary amount as an integer count of the currency's minor unit (e.g.
 * cents), plus an explicit currency code. Immutable — every operation
 * returns a new instance.
 *
 * Introduced by docs/ADR/0002-money-format-migration.md to replace ad-hoc
 * bcadd/bcsub string arithmetic scattered across Services, and to give every
 * money-bearing model one shared, tested implementation of the rounding
 * rules already decided for VAT (per-line, half-up, allocated so parts
 * always sum exactly to the original — see allocate() below).
 *
 * Does NOT cover the Inventory module's `unit_cost`/`average_cost` "micros"
 * columns (integer x1,000,000, i.e. 4 decimal places beyond the minor unit)
 * — those are intermediate costing precision, not a monetary amount meant
 * for display or arithmetic with other money. They convert to a Money only
 * at the point they become a real, invoice-comparable amount (e.g.
 * quantity x unit_cost_micros / 10_000 -> total_cost, in minor units).
 */
final class Money
{
    private function __construct(
        private readonly int $minorUnits,
        private readonly string $currency,
    ) {}

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * Only for reading legacy decimal columns during the dual-write
     * transition (docs/ADR/0002-money-format-migration.md Section 4) — new
     * code should read the `_minor` column directly via fromMinorUnits().
     * Accepts string|float|int|null the same way Eloquent decimal
     * attributes come back from the query builder.
     */
    public static function fromDecimal(string|float|int|null $amount, string $currency): self
    {
        $amount ??= 0;

        // Round half-up at the minor unit, not whatever naive float
        // rounding would otherwise do — matches the VAT per-line rounding
        // rule already decided in docs/ADR/0001-consolidation.md Appendix
        // A1. Relies on PHP's round() built-in precision correction for the
        // classic "0.285 * 100 isn't exactly 28.5 in binary float" problem
        // (documented PHP behavior since 5.3, not something this class
        // re-implements) — safe here because the realistic input is a
        // decimal(x,2) column's string value, not an arbitrarily-computed
        // float.
        $minorUnits = (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);

        return new self($minorUnits, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Decimal string representation for display or for writing to a legacy
     * decimal column during the transition — never for further arithmetic
     * (arithmetic stays in minor units to avoid reintroducing float error).
     */
    public function toDecimalString(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $abs = abs($this->minorUnits);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Multiplies by a quantity (e.g. unit_price x quantity). Rounds half-up
     * to the nearest minor unit — the same rule used everywhere else, so a
     * line total computed this way is consistent with allocate()'s parts.
     */
    public function multiply(float|int|string $factor): self
    {
        // bcmul keeps the multiplication itself exact — doing
        // $this->minorUnits * (float) $factor directly would reintroduce
        // the binary-float-imprecision problem this class exists to
        // eliminate (e.g. a rate like 0.05 has no exact binary
        // representation). Only the final round-to-nearest-integer step
        // touches float, which is safe: a float carries ~15-17
        // significant decimal digits, far more precision than needed to
        // round a bcmul result (kept to 10 decimal places) correctly.
        $product = bcmul((string) $this->minorUnits, (string) $factor, 10);
        $result = (int) round((float) $product, 0, PHP_ROUND_HALF_UP);

        return new self($result, $this->currency);
    }

    /**
     * Same as multiply(), but truncates toward zero instead of rounding.
     * Exists specifically to preserve pre-existing behavior when porting
     * calculations that used to run through bcmath's bcmul($a, $b, 2) —
     * bcmath's scale parameter truncates, it does not round, so a
     * cutover using multiply() instead of this method would silently
     * change already-computed amounts (e.g. statutory tax/withholding
     * figures) by a cent in some cases. Prefer multiply() for new
     * calculations; use this only when matching legacy truncation
     * behavior is the explicit goal.
     */
    public function multiplyTruncate(float|int|string $factor): self
    {
        $product = bcmul((string) $this->minorUnits, (string) $factor, 10);
        $result = (int) bcadd($product, '0', 0); // truncates toward zero, like bcmath's own scale

        return new self($result, $this->currency);
    }

    /**
     * Splits this amount across N parts by the given weights (e.g.
     * allocating a document-level discount across its line items by each
     * line's share of the subtotal), using the largest-remainder method so
     * the parts always sum to exactly this amount — no line silently
     * absorbs a rounding difference, per docs/ADR/0001-consolidation.md
     * Appendix A1 and docs/ADR/0002-money-format-migration.md Section 5.
     *
     * @param  array<int, float|int|string>  $weights  must not all be zero
     * @return array<int, self> same length/order as $weights
     */
    public function allocate(array $weights): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('Cannot allocate across zero weights.');
        }

        $totalWeight = array_sum(array_map(static fn ($w) => (float) $w, $weights));

        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Weights must sum to a positive number.');
        }

        $rawShares = [];
        $flooredShares = [];
        $flooredSum = 0;

        foreach ($weights as $i => $weight) {
            $raw = ($this->minorUnits * (float) $weight) / $totalWeight;
            $rawShares[$i] = $raw;
            $flooredShares[$i] = (int) floor($raw);
            $flooredSum += $flooredShares[$i];
        }

        // Distribute the remainder (always < count($weights) minor units)
        // one unit at a time to the shares with the largest fractional
        // remainder, so the total matches exactly.
        $remainder = $this->minorUnits - $flooredSum;
        $remainders = [];
        foreach ($rawShares as $i => $raw) {
            $remainders[$i] = $raw - floor($raw);
        }
        arsort($remainders);

        $result = $flooredShares;
        foreach (array_keys($remainders) as $i) {
            if ($remainder <= 0) {
                break;
            }
            $result[$i]++;
            $remainder--;
        }

        return array_map(fn (int $minor) => new self($minor, $this->currency), $result);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on Money in different currencies ({$this->currency} vs {$other->currency})."
            );
        }
    }
}
