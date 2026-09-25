<?php

namespace Tests;

use App\Http\Middleware\EnsureSetupComplete;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // The shell environment may export APP_ENV / DB_* values that would
        // otherwise leak into tests (PHPUnit only overrides $_ENV + putenv, not
        // $_SERVER). Force the isolated in-memory sqlite database in every
        // environment source Laravel reads so migrate:fresh never touches the
        // development database.
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = parent::createApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                "Refusing to run tests: default connection resolves to [{$connection}]/{$database}. "
                .'Tests must run against an in-memory sqlite database so migrate:fresh never touches the development database.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Most tests don't care about the first-run setup wizard; treat the
        // instance as set up. Setup wizard tests call markSetupIncomplete().
        Cache::forever(EnsureSetupComplete::CACHE_KEY, true);
    }

    protected function markSetupIncomplete(): void
    {
        Cache::forget(EnsureSetupComplete::CACHE_KEY);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
