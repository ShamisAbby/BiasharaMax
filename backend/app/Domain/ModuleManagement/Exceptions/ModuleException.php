<?php

namespace App\Domain\ModuleManagement\Exceptions;

use RuntimeException;

class ModuleException extends RuntimeException
{
    public static function inUse(string $name): self
    {
        return new self("\"{$name}\" is installed on at least one business and cannot be deleted. Disable it instead.");
    }

    /**
     * @param  array<int, string>  $missingDependencyNames
     */
    public static function missingDependencies(array $missingDependencyNames): self
    {
        $list = implode(', ', $missingDependencyNames);

        return new self("This module requires the following to be enabled first: {$list}.");
    }
}
