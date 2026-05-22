<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\SharedLink;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SharingDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('beacon.sharing.enabled', false);
    }

    public function test_share_routes_are_not_registered_when_sharing_is_disabled(): void
    {
        $incoming = $this->makeIncoming();

        // The share route is absent, so the request does not reach ShareController.
        $this->postJson('/beacon/api/shares', [
            'request_type' => 'incoming',
            'request_id' => $incoming->id,
            'expiry' => 'never',
        ]);

        $this->assertSame(0, SharedLink::query()->count());
    }

    public function test_shared_view_route_is_not_registered_when_sharing_is_disabled(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_type' => 'incoming', 'request_id' => $incoming->id]);

        // Without the route, the gated catch-all serves the SPA shell (HTML),
        // never the JSON shared payload.
        $response = $this->get('/beacon/api/shared/'.$share->token);

        $this->assertStringNotContainsString('"status":"ok"', (string) $response->getContent());
    }
}
