<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\SharedLink;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ShareControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_share_with_token_and_url(): void
    {
        $incoming = $this->makeIncoming();

        $response = $this->postJson('/beacon/api/shares', [
            'request_type' => 'incoming',
            'request_id' => $incoming->id,
            'expiry' => 'never',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertStringContainsString('/beacon/shared/', $response->json('data.url'));
        $this->assertFalse($response->json('data.has_password'));
        $this->assertNull($response->json('data.expires_at'));
        $this->assertSame('active', $response->json('data.status'));

        $this->assertSame(1, SharedLink::query()->count());
    }

    public function test_store_with_password_hashes_it_and_sets_expiry(): void
    {
        $incoming = $this->makeIncoming();

        $response = $this->postJson('/beacon/api/shares', [
            'request_type' => 'incoming',
            'request_id' => $incoming->id,
            'expiry' => '24h',
            'password' => 'secret',
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('data.has_password'));
        $this->assertNotNull($response->json('data.expires_at'));

        $share = SharedLink::query()->first();
        $this->assertNotSame('secret', $share->password);
        $this->assertTrue(Hash::check('secret', $share->password));
    }

    public function test_store_rejects_an_invalid_request_type(): void
    {
        $incoming = $this->makeIncoming();

        $this->postJson('/beacon/api/shares', [
            'request_type' => 'sideways',
            'request_id' => $incoming->id,
            'expiry' => 'never',
        ])->assertStatus(422);

        $this->assertSame(0, SharedLink::query()->count());
    }

    public function test_store_rejects_an_unknown_expiry_preset(): void
    {
        $incoming = $this->makeIncoming();

        $this->postJson('/beacon/api/shares', [
            'request_type' => 'incoming',
            'request_id' => $incoming->id,
            'expiry' => 'forever-and-ever',
        ])->assertStatus(422);

        $this->assertSame(0, SharedLink::query()->count());
    }

    public function test_store_for_a_missing_request_returns_404(): void
    {
        $this->postJson('/beacon/api/shares', [
            'request_type' => 'incoming',
            'request_id' => 9999,
            'expiry' => 'never',
        ])->assertNotFound();

        $this->assertSame(0, SharedLink::query()->count());
    }

    public function test_index_filters_by_request(): void
    {
        $a = $this->makeIncoming();
        $b = $this->makeIncoming();

        $this->makeShare(['request_type' => 'incoming', 'request_id' => $a->id]);
        $this->makeShare(['request_type' => 'incoming', 'request_id' => $a->id]);
        $this->makeShare(['request_type' => 'incoming', 'request_id' => $b->id]);

        $filtered = $this->getJson('/beacon/api/shares?request_type=incoming&request_id='.$a->id);
        $filtered->assertOk();
        $this->assertCount(2, $filtered->json('data'));

        $all = $this->getJson('/beacon/api/shares');
        $this->assertCount(3, $all->json('data'));
    }

    public function test_destroy_revokes_the_share(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_type' => 'incoming', 'request_id' => $incoming->id]);

        $response = $this->deleteJson('/beacon/api/shares/'.$share->id);

        $response->assertOk();
        $this->assertSame('revoked', $response->json('data.status'));
        $this->assertNotNull($share->fresh()->revoked_at);
    }
}
