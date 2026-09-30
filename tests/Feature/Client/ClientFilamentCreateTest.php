<?php

namespace Tests\Feature\Client;

use App\Actions\Client\SyncClientAccount;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientFilamentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographer_can_create_a_client_with_an_account_from_filament(): void
    {
        $photographer = User::factory()->create();
        $this->actingAs($photographer);
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.test',
            ])
            ->assertDontSee('Client Account Password')
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::query()->where('email', 'cliente@example.test')->firstOrFail();

        $this->assertSame($photographer->id, $client->user_id);
        $this->assertNotNull($client->clientAccount);
        $this->assertSame('cliente@example.test', $client->clientAccount->email);
    }

    public function test_photographer_can_edit_a_client_without_providing_a_password(): void
    {
        $photographer = User::factory()->create();
        $client = Client::factory()->for($photographer)->create();
        $account = app(SyncClientAccount::class)->handle($client);
        $passwordHash = $account->password;
        $this->actingAs($photographer);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['name' => 'Nome atualizado'])
            ->assertDontSee('Client Account Password')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nome atualizado', $client->fresh()->name);
        $this->assertSame($passwordHash, $account->fresh()->password);
    }
}
