<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;

class IntegrationService
{
    public function __construct(
        private readonly IntegrationDriverResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Integration
    {
        return Integration::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Integration $integration, array $data): Integration
    {
        $integration->update($data);

        return $integration->refresh();
    }

    public function enable(Integration $integration): Integration
    {
        $integration->update(['is_enabled' => true]);

        return $integration->refresh();
    }

    public function disable(Integration $integration): Integration
    {
        $integration->update(['is_enabled' => false]);

        return $integration->refresh();
    }

    public function testConnection(Integration $integration): IntegrationLog
    {
        try {
            $result = $this->resolver->resolve($integration)->test();
        } catch (\Throwable $e) {
            $result = ['successful' => false, 'status_code' => null, 'response' => [], 'error' => $e->getMessage()];
        }

        $log = IntegrationLog::query()->create([
            'integration_id' => $integration->id,
            'direction' => IntegrationLog::DIRECTION_OUTBOUND,
            'event_type' => 'connection_test',
            'response_payload' => $result['response'],
            'status_code' => $result['status_code'],
            'is_successful' => $result['successful'],
            'error_message' => $result['error'],
        ]);

        $integration->update([
            'last_tested_at' => now(),
            'last_test_result' => $result['successful'] ? Integration::TEST_RESULT_SUCCESS : Integration::TEST_RESULT_FAILED,
        ]);

        return $log;
    }
}
