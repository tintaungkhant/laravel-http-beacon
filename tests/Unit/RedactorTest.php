<?php

namespace HttpBeacon\Tests\Unit;

use HttpBeacon\Support\Redactor;
use HttpBeacon\Tests\TestCase;

class RedactorTest extends TestCase
{
    public function test_masks_hidden_headers_case_insensitively(): void
    {
        config()->set('beacon.hidden_headers', ['authorization', 'x-api-key']);

        $result = Redactor::headers([
            'Authorization' => ['Bearer abc123'],
            'X-API-Key' => 'secret-key',
            'X-Foo' => ['bar'],
        ]);

        $this->assertSame('********', $result['authorization']);
        $this->assertSame('********', $result['x-api-key']);
        $this->assertSame('bar', $result['x-foo']);
    }

    public function test_masks_parameters_via_dot_path_and_wildcard(): void
    {
        config()->set('beacon.hidden_parameters', ['user.password', 'tokens.*.value']);

        $result = Redactor::parameters([
            'user' => ['password' => 'secret', 'name' => 'tint'],
            'tokens' => [
                ['value' => 'aaa', 'kind' => 'access'],
                ['value' => 'bbb', 'kind' => 'refresh'],
            ],
        ]);

        $this->assertSame('********', $result['user']['password']);
        $this->assertSame('tint', $result['user']['name']);
        $this->assertSame('********', $result['tokens'][0]['value']);
        $this->assertSame('access', $result['tokens'][0]['kind']);
        $this->assertSame('********', $result['tokens'][1]['value']);
    }

    public function test_does_nothing_when_redact_disabled(): void
    {
        config()->set('beacon.redact', false);
        config()->set('beacon.hidden_headers', ['authorization']);
        config()->set('beacon.hidden_parameters', ['password']);

        $headers = Redactor::headers(['Authorization' => 'Bearer abc']);
        $params = Redactor::parameters(['password' => 'secret']);

        $this->assertSame('Bearer abc', $headers['authorization']);
        $this->assertSame('secret', $params['password']);
    }
}
