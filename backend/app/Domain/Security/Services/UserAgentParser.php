<?php

namespace App\Domain\Security\Services;

use Jenssegers\Agent\Agent;

/**
 * Thin wrapper around jenssegers/agent — real parsing of the `user_agent`
 * string already captured on every request, not a fabricated guess.
 */
class UserAgentParser
{
    /**
     * @return array{browser: ?string, operating_system: ?string, device_type: string}
     */
    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return ['browser' => null, 'operating_system' => null, 'device_type' => 'unknown'];
        }

        $agent = new Agent();
        $agent->setUserAgent($userAgent);

        $deviceType = match (true) {
            $agent->isRobot() => 'bot',
            $agent->isTablet() => 'tablet',
            $agent->isMobile() => 'mobile',
            default => 'desktop',
        };

        return [
            'browser' => $agent->browser() ?: null,
            'operating_system' => $agent->platform() ?: null,
            'device_type' => $deviceType,
        ];
    }
}
