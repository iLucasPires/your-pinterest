<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Actions\Client\SyncClientAccount;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Client $client */
        $client = $this->record;

        app(SyncClientAccount::class)->handle($client);
    }
}
