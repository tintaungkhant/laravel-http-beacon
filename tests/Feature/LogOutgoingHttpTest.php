<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class LogOutgoingHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_successful_outgoing_response(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200, ['Content-Type' => 'application/json']),
        ]);

        Http::get('https://api.example.com/users');

        $this->assertSame(1, OutgoingRequest::query()->count());
        $row = OutgoingRequest::query()->first();
        $this->assertSame('GET', $row->method);
        $this->assertSame('api.example.com', $row->hostname);
        $this->assertSame(200, $row->status);
        $this->assertFalse($row->failed);
    }

    public function test_redacts_sensitive_request_headers(): void
    {
        config()->set('beacon.hidden_headers', ['authorization']);
        Http::fake(['*' => Http::response('', 204)]);

        Http::withHeaders(['Authorization' => 'Bearer secret-token'])
            ->get('https://api.example.com/private');

        $row = OutgoingRequest::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('********', $row->request_headers['authorization']);
    }
}
