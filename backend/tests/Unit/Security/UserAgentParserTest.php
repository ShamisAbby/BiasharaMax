<?php

namespace Tests\Unit\Security;

use App\Domain\Security\Services\UserAgentParser;
use Tests\TestCase;

class UserAgentParserTest extends TestCase
{
    private UserAgentParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new UserAgentParser();
    }

    public function test_parses_a_real_chrome_desktop_user_agent(): void
    {
        $result = $this->parser->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $this->assertSame('Chrome', $result['browser']);
        $this->assertSame('Windows', $result['operating_system']);
        $this->assertSame('desktop', $result['device_type']);
    }

    public function test_parses_a_real_mobile_user_agent(): void
    {
        $result = $this->parser->parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');

        $this->assertSame('mobile', $result['device_type']);
        $this->assertSame('iOS', $result['operating_system']);
    }

    public function test_null_user_agent_returns_unknown_without_throwing(): void
    {
        $result = $this->parser->parse(null);

        $this->assertNull($result['browser']);
        $this->assertNull($result['operating_system']);
        $this->assertSame('unknown', $result['device_type']);
    }
}
