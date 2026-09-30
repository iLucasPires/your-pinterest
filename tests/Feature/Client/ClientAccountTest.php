<?php

namespace Tests\Feature\Client;

use App\Actions\Client\SyncClientAccount;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_account_password_is_hashed_and_can_be_rotated(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create([
            'email' => 'client@example.test',
        ]);

        $account = app(SyncClientAccount::class)->handle($client, 'FirstRandomPassword!');

        $this->assertNotNull($account);
        $this->assertNotSame('FirstRandomPassword!', $account->password);
        $this->assertTrue(Hash::check('FirstRandomPassword!', $account->password));
        $this->assertTrue($account->is_client_account);
        $this->assertSame($account->id, $client->fresh()->client_user_id);

        app(SyncClientAccount::class)->handle($client->fresh(), 'SecondRandomPassword!');

        $account->refresh();
        $this->assertTrue(Hash::check('SecondRandomPassword!', $account->password));
        $this->assertFalse(Hash::check('FirstRandomPassword!', $account->password));
    }
}
