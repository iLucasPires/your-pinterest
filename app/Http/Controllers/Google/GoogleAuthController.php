<?php

namespace App\Http\Controllers\Google;

use App\Actions\Google\DisconnectGoogle;
use App\Actions\Google\UpsertGoogleConnection;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()
            ->put('google_oauth_intended', url()->previous());

        return $this->googleProvider()
            ->scopes(['https://www.googleapis.com/auth/drive.readonly'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    /**
     * Handle the callback from Google after the user grants permission.
     */
    public function callback(Request $request, UpsertGoogleConnection $action): RedirectResponse
    {
        try {
            $socialiteUser = $this->googleProvider()->user();
        } catch (\Throwable $e) {
            return redirect()
                ->route('filament.admin.pages.google-drive')
                ->with('error', 'Google authentication failed. Please try again.');
        }

        $action->handle($request->user(), $socialiteUser);

        return redirect()
            ->route('filament.admin.pages.google-drive')
            ->with('success', 'Google Drive connected successfully.');
    }

    /**
     * Disconnect the current user's Google connection.
     */
    public function disconnect(Request $request, DisconnectGoogle $action): RedirectResponse
    {
        $action->handle($request->user());

        return redirect()
            ->route('filament.admin.pages.google-drive')
            ->with('success', 'Google Drive disconnected.');
    }

    private function googleProvider(): GoogleProvider
    {
        return Socialite::driver('google');
    }
}
