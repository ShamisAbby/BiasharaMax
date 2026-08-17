<?php

namespace App\Domain\Website\Exceptions;

use Exception;

class CheckoutException extends Exception
{
    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    public static function noFulfilmentLocation(): self
    {
        return new self('This business has no branch set up to fulfil online orders yet.');
    }
}
