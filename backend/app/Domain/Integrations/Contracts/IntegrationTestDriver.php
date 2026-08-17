<?php

namespace App\Domain\Integrations\Contracts;

interface IntegrationTestDriver
{
    /**
     * @return array{successful: bool, status_code: ?int, response: array<string, mixed>, error: ?string}
     */
    public function test(): array;
}
