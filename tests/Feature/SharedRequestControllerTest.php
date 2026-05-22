<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class SharedRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_a_valid_share_returns_the_request_and_bumps_view_count(): void
    {
        $incoming = $this->makeIncoming(['path' => '/api/widgets']);
        $share = $this->makeShare(['request_type' => 'incoming', 'request_id' => $incoming->id]);

        $response = $this->getJson('/beacon/api/shared/'.$share->token);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.type', 'incoming');
        $response->assertJsonPath('data.request.path', '/api/widgets');

        $fresh = $share->fresh();
        $this->assertSame(1, $fresh->view_count);
        $this->assertNotNull($fresh->last_viewed_at);
    }

    public function test_viewing_an_outgoing_share_returns_the_outgoing_request(): void
    {
        $outgoing = $this->makeOutgoing(['uri' => 'https://api.example.com/v1/ping']);
        $share = $this->makeShare(['request_type' => 'outgoing', 'request_id' => $outgoing->id]);

        $this->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.type', 'outgoing')
            ->assertJsonPath('data.request.uri', 'https://api.example.com/v1/ping');
    }

    public function test_revoked_share_reports_revoked(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'revoked_at' => now()]);

        $this->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_expired_share_reports_expired(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'expires_at' => now()->subHour()]);

        $this->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');
    }

    public function test_share_whose_request_was_deleted_reports_missing(): void
    {
        $share = $this->makeShare(['request_type' => 'incoming', 'request_id' => 4242]);

        $this->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'missing');
    }

    public function test_unknown_token_reports_missing(): void
    {
        $this->getJson('/beacon/api/shared/does-not-exist')
            ->assertOk()
            ->assertJsonPath('data.status', 'missing');
    }

    public function test_password_protected_share_reports_locked(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'password' => Hash::make('secret')]);

        $this->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'locked');
    }

    public function test_unlock_with_wrong_password_is_rejected(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'password' => Hash::make('secret')]);

        $this->postJson('/beacon/api/shared/'.$share->token.'/unlock', ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('data.unlocked', false);
    }

    public function test_unlock_with_correct_password_succeeds(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'password' => Hash::make('secret')]);

        $this->postJson('/beacon/api/shared/'.$share->token.'/unlock', ['password' => 'secret'])
            ->assertOk()
            ->assertJsonPath('data.unlocked', true);
    }

    public function test_unlock_is_rate_limited(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'password' => Hash::make('secret')]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/beacon/api/shared/'.$share->token.'/unlock', ['password' => 'wrong']);
        }

        $this->postJson('/beacon/api/shared/'.$share->token.'/unlock', ['password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_unlocked_session_reveals_the_request(): void
    {
        $incoming = $this->makeIncoming();
        $share = $this->makeShare(['request_id' => $incoming->id, 'password' => Hash::make('secret')]);

        $this->withSession(['beacon.shared.'.$share->token => true])
            ->getJson('/beacon/api/shared/'.$share->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }
}
