<?php

namespace Tests\Unit\Shared;

use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for the Money value object introduced by
 * docs/ADR/0002-money-format-migration.md — no database needed. Written
 * carefully by hand since the environment this was authored in has no PHP
 * runtime to actually execute these; run `php artisan test --filter=MoneyTest`
 * before trusting the rounding/allocation logic.
 */
class MoneyTest extends TestCase
{
    public function test_from_decimal_converts_to_minor_units(): void
    {
        $money = Money::fromDecimal('123.45', 'TZS');

        $this->assertSame(12345, $money->minorUnits());
        $this->assertSame('TZS', $money->currency());
    }

    public function test_from_decimal_rounds_half_up(): void
    {
        // 10.005 -> 1000.5 minor units -> rounds up to 1001, not banker's
        // rounding down to 1000 — matches the VAT half-up rule.
        $this->assertSame(1001, Money::fromDecimal('10.005', 'TZS')->minorUnits());
        $this->assertSame(1000, Money::fromDecimal('10.004', 'TZS')->minorUnits());
    }

    public function test_from_decimal_handles_null_as_zero(): void
    {
        $this->assertTrue(Money::fromDecimal(null, 'TZS')->isZero());
    }

    public function test_to_decimal_string_round_trips(): void
    {
        $this->assertSame('123.45', Money::fromMinorUnits(12345, 'TZS')->toDecimalString());
        $this->assertSame('0.05', Money::fromMinorUnits(5, 'TZS')->toDecimalString());
        $this->assertSame('100.00', Money::fromMinorUnits(10000, 'TZS')->toDecimalString());
    }

    public function test_to_decimal_string_handles_negative_amounts(): void
    {
        $this->assertSame('-5.50', Money::fromMinorUnits(-550, 'TZS')->toDecimalString());
    }

    public function test_add_and_subtract(): void
    {
        $a = Money::fromMinorUnits(1000, 'TZS');
        $b = Money::fromMinorUnits(300, 'TZS');

        $this->assertSame(1300, $a->add($b)->minorUnits());
        $this->assertSame(700, $a->subtract($b)->minorUnits());
    }

    public function test_add_rejects_mismatched_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(100, 'TZS')->add(Money::fromMinorUnits(100, 'KES'));
    }

    public function test_multiply_rounds_half_up(): void
    {
        // 3 units at 333 minor units each = 999, no rounding needed.
        $this->assertSame(999, Money::fromMinorUnits(333, 'TZS')->multiply(3)->minorUnits());

        // 150 minor units x 0.5 = 75.0, exact.
        $this->assertSame(75, Money::fromMinorUnits(150, 'TZS')->multiply('0.5')->minorUnits());

        // 100 minor units x 1/3 = 33.33... -> rounds to 33.
        $this->assertSame(33, Money::fromMinorUnits(100, 'TZS')->multiply(1 / 3)->minorUnits());
    }

    public function test_multiply_truncate_matches_legacy_bcmul_scale_2_behavior(): void
    {
        // bcmath's bcmul($a, $b, 2) truncates, it does not round. 1000
        // minor units x 0.0505 = 50.5 exactly -> legacy behavior truncates
        // to 50, not 51.
        $this->assertSame(50, Money::fromMinorUnits(1000, 'TZS')->multiplyTruncate('0.0505')->minorUnits());

        // Same input via multiply() (rounding) gives a different answer —
        // this is exactly why multiplyTruncate() exists as a distinct
        // method rather than reusing multiply() when porting legacy code.
        $this->assertSame(51, Money::fromMinorUnits(1000, 'TZS')->multiply('0.0505')->minorUnits());
    }

    public function test_multiply_truncate_exact_cases_match_rounding(): void
    {
        // When there's no fractional remainder, truncate and round agree.
        $this->assertSame(50, Money::fromMinorUnits(1000, 'TZS')->multiplyTruncate('0.05')->minorUnits());
        $this->assertSame(50, Money::fromMinorUnits(1000, 'TZS')->multiply('0.05')->minorUnits());
    }

    public function test_allocate_splits_exactly_with_no_leftover(): void
    {
        // Classic case: 100 minor units split 3 ways can't divide evenly
        // (33.33 each) — largest-remainder method must still sum to 100.
        $parts = Money::fromMinorUnits(100, 'TZS')->allocate([1, 1, 1]);

        $this->assertCount(3, $parts);
        $sum = array_sum(array_map(fn (Money $m) => $m->minorUnits(), $parts));
        $this->assertSame(100, $sum);

        // Each part should be 33 or 34, never 32 or 35.
        foreach ($parts as $part) {
            $this->assertContains($part->minorUnits(), [33, 34]);
        }
    }

    public function test_allocate_by_weighted_shares(): void
    {
        // A 1000 minor-unit discount split across two lines worth 700 and
        // 300 (weights proportional to line subtotal) -> 700 and 300 exactly.
        $parts = Money::fromMinorUnits(1000, 'TZS')->allocate([700, 300]);

        $this->assertSame(700, $parts[0]->minorUnits());
        $this->assertSame(300, $parts[1]->minorUnits());
    }

    public function test_allocate_rejects_all_zero_weights(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(100, 'TZS')->allocate([0, 0]);
    }

    public function test_zero_positive_negative_predicates(): void
    {
        $this->assertTrue(Money::zero('TZS')->isZero());
        $this->assertTrue(Money::fromMinorUnits(1, 'TZS')->isPositive());
        $this->assertTrue(Money::fromMinorUnits(-1, 'TZS')->isNegative());
    }

    public function test_equals_compares_amount_and_currency(): void
    {
        $this->assertTrue(Money::fromMinorUnits(100, 'TZS')->equals(Money::fromMinorUnits(100, 'TZS')));
        $this->assertFalse(Money::fromMinorUnits(100, 'TZS')->equals(Money::fromMinorUnits(100, 'KES')));
        $this->assertFalse(Money::fromMinorUnits(100, 'TZS')->equals(Money::fromMinorUnits(101, 'TZS')));
    }
}
