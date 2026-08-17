<?php

namespace App\Domain\Integrations\Exceptions;

use App\Domain\Integrations\Models\Integration;
use RuntimeException;

class IntegrationNotConfiguredException extends RuntimeException
{
    public static function forIntegration(Integration $integration): self
    {
        return new self("Integration \"{$integration->name}\" is not enabled or has no credentials configured.");
    }
}
