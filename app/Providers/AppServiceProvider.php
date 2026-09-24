<?php

namespace App\Providers;

use App\Services\AiCredentialManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
        $this->syncAiProviderCredentials();
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for the MCP endpoint.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
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

    protected function configureDevCommands(): void
    {
        DevCommands::artisan('serve --port=8001', 'server');
        DevCommands::artisan('schedule:work', 'scheduler');
        DevCommands::artisan('horizon:listen', 'horizon');
        DevCommands::artisan('mcp:inspector mcp', 'mcp');

        if (App::environment('local')) {
            DevCommands::register(
                'opencode serve --hostname 0.0.0.0',
                'opencode'
            )->purple();
        }
    }

    /**
     * Inject encrypted DB credentials into ai.providers runtime config.
     *
     * Guarded inside the manager so missing tables (fresh installs,
     * config:cache) never break boot.
     */
    protected function syncAiProviderCredentials(): void
    {
        app(AiCredentialManager::class)->syncConfig();
    }
}
