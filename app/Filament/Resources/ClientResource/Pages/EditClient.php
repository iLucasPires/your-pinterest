<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Actions\Client\SyncClientAccount;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        /** @var Client $client */
        $client = $this->record;

        app(SyncClientAccount::class)->handle($client);
    }
}
