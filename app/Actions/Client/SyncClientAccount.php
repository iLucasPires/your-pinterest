<?php

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncClientAccount
{
    public function handle(Client $client, ?string $plainPassword = null): ?User
    {
        if (blank($client->email)) {
            $client->clientAccount()->dissociate()->save();

            return null;
        }

        $email = Str::lower(trim($client->email));
        $account = $client->clientAccount;

        if (! $account || Str::lower($account->email) !== $email) {
            $account = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if ($account && ! $account->is_client_account) {
            throw ValidationException::withMessages([
                'data.email' => 'Este e-mail já pertence a uma conta de fotógrafo.',
            ]);
        }

        $password = $plainPassword ?? ($account ? null : Str::random(16));
        $attributes = [
            'name' => $client->name,
            'email' => $email,
            'is_client_account' => true,
        ];

        if ($password !== null) {
            $attributes['password'] = $password;
        }

        if ($account) {
            $account->update($attributes);
        } else {
            $account = User::create($attributes);
        }

        $client->clientAccount()->associate($account)->save();

        return $account;
    }
}
