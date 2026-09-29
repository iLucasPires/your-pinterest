<?php

namespace Tests\Feature\Google;

use App\Actions\Google\DisconnectGoogle;
use App\Actions\Google\UpsertGoogleConnection;
use App\Models\GoogleConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_creates_connection_on_first_connect(): void
    {
        $user = User::factory()->create();

        $socialiteUser = $this->makeSocialiteUser();

        app(UpsertGoogleConnection::class)->handle($user, $socialiteUser);

        $this->assertDatabaseHas('google_connections', [
            'user_id'      => $user->id,
            'google_id'    => 'google-uid-123',
            'google_email' => 'photographer@gmail.com',
        ]);
    }

    public function test_upsert_updates_tokens_on_reconnect(): void
    {
        $user = User::factory()->create();

        GoogleConnection::create([
            'user_id'      => $user->id,
            'google_id'    => 'google-uid-123',
            'google_email' => 'photographer@gmail.com',
            'access_token'  => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
        ]);

        $socialiteUser = $this->makeSocialiteUser('new-access-token');

        app(UpsertGoogleConnection::class)->handle($user, $socialiteUser);

        $this->assertCount(1, GoogleConnection::where('user_id', $user->id)->get());

        // Token is encrypted — just check the record updated (not plaintext)
        $connection = GoogleConnection::where('user_id', $user->id)->first();
        $this->assertEquals('new-access-token', $connection->access_token);
    }

    public function test_disconnect_removes_connection(): void
    {
        $user = User::factory()->create();
        GoogleConnection::create([
            'user_id'       => $user->id,
            'google_id'     => 'google-uid-123',
            'google_email'  => 'photographer@gmail.com',
            'access_token'  => 'token',
            'refresh_token' => 'refresh',
        ]);

        app(DisconnectGoogle::class)->handle($user);

        $this->assertDatabaseMissing('google_connections', ['user_id' => $user->id]);
    }

    public function test_connection_is_expired_when_token_expires_at_is_past(): void
    {
        $connection = new GoogleConnection([
            'token_expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($connection->isExpired());
    }

    public function test_connection_is_not_expired_when_token_has_time_remaining(): void
    {
        $connection = new GoogleConnection([
            'token_expires_at' => now()->addHour(),
        ]);

        $this->assertFalse($connection->isExpired());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSocialiteUser(string $accessToken = 'access-token-abc'): SocialiteUser
    {
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('google-uid-123');
        $mock->shouldReceive('getEmail')->andReturn('photographer@gmail.com');
        $mock->token        = $accessToken;
        $mock->refreshToken = 'refresh-token-xyz';
        $mock->expiresIn    = 3600;

        return $mock;
    }
}
