<?php

namespace App\Http\Controllers\Google;

use App\Jobs\SyncGalleryDrivePermissions;
use App\Actions\Google\DisconnectGoogle;
use App\Actions\Google\UpsertGoogleConnection;
use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use App\Models\User;
use App\Services\Gallery\GalleryAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('gallery_google_login');
        $request->session()
            ->put('google_oauth_intended', url()->previous());

        return $this->googleProvider()
            ->scopes(['https://www.googleapis.com/auth/drive'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    /**
     * Handle the callback from Google after the user grants permission.
     */
    public function callback(
        Request $request,
        UpsertGoogleConnection $action,
        GalleryAccessService $galleryAccess,
    ): RedirectResponse
    {
        $galleryLogin = $request->session()->pull('gallery_google_login');

        try {
            $socialiteUser = $this->googleProvider()->user();
        } catch (\Throwable $e) {
            if ($galleryLogin !== null) {
                return redirect()
                    ->route('gallery.login', $galleryLogin['slug'])
                    ->withErrors(['email' => 'O login com Google não foi concluído. Tente novamente.']);
            }

            return redirect()
                ->route('filament.admin.pages.google-drive')
                ->with('error', 'Google authentication failed. Please try again.');
        }

        if ($galleryLogin !== null) {
            return $this->completeGalleryLogin($request, $galleryLogin, $socialiteUser, $galleryAccess);
        }

        if (! $request->user()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $action->handle($request->user(), $socialiteUser);
        SyncGalleryDrivePermissions::dispatch($request->user()->id)->afterCommit();

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

    /** @param array{gallery_id: int, slug: string} $galleryLogin */
    private function completeGalleryLogin(
        Request $request,
        array $galleryLogin,
        SocialiteUser $socialiteUser,
        GalleryAccessService $galleryAccess,
    ): RedirectResponse {
        $gallery = Gallery::query()
            ->with('client')
            ->whereKey($galleryLogin['gallery_id'])
            ->where('slug', $galleryLogin['slug'])
            ->where('is_published', true)
            ->where('access_type', Gallery::ACCESS_PRIVATE)
            ->firstOrFail();

        $email = $socialiteUser->getEmail();
        $verified = filter_var($socialiteUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $email || ! $verified || ! $galleryAccess->isAuthorizedEmail($gallery, $email)) {
            return redirect()
                ->route('gallery.login', $gallery->slug)
                ->withErrors(['email' => 'A conta Google não corresponde ao e-mail autorizado.']);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialiteUser->getName() ?: $email,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Str::random(64),
                'is_client_account' => true,
            ]);
        }

        if ($user->is_client_account && $gallery->client) {
            $gallery->client->clientAccount()->associate($user)->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('gallery.show', $gallery->slug);
    }

    private function googleProvider(): GoogleProvider
    {
        return Socialite::driver('google');
    }
}
