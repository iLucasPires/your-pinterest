<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Actions\Gallery\SyncGalleryFromDrive;
use App\Services\Google\GoogleDriveProviderFactory;
use App\Policies\ClientPolicy;
use App\Policies\GalleryPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the factory as a singleton so tests can swap it out easily
        $this->app->singleton(GoogleDriveProviderFactory::class);

        // Bind SyncGalleryFromDrive so its dependencies are resolved by the container
        $this->app->bind(SyncGalleryFromDrive::class, function ($app) {
            return new SyncGalleryFromDrive($app->make(GoogleDriveProviderFactory::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register authorization policies.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(Gallery::class, GalleryPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
    }
}
