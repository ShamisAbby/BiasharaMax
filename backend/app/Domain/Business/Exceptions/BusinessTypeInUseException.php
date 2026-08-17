<?php

namespace App\Domain\Business\Exceptions;

use RuntimeException;

class BusinessTypeInUseException extends RuntimeException
{
    public static function forType(string $name): self
    {
        return new self("\"{$name}\" has businesses assigned to it and cannot be deleted. Archive it instead.");
    }
}
