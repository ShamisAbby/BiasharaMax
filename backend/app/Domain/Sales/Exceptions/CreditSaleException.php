<?php

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

class CreditSaleException extends RuntimeException
{
    public static function customerRequired(): self
    {
        return new self('Credit sales require a customer to be selected.');
    }

    public static function customerNotOnCreditTerms(string $customerName): self
    {
        return new self("\"{$customerName}\" is a cash customer and cannot be sold to on credit.");
    }

    public static function creditLimitExceeded(string $customerName, string $limit, string $wouldBeBalance): self
    {
        return new self(
            "\"{$customerName}\"'s credit limit is {$limit}; this sale would bring their balance to {$wouldBeBalance}."
        );
    }

    public static function alreadyVoided(): self
    {
        return new self('This sale has already been voided.');
    }

    public static function paymentExceedsBalance(string $balanceDue): self
    {
        return new self("Payment amount exceeds the outstanding balance of {$balanceDue}.");
    }
}
