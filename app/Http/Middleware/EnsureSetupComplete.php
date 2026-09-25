<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every request to the setup wizard until the first admin exists.
 */
class EnsureSetupComplete
{
    public const CACHE_KEY = 'straden.setup_complete';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isComplete() || $request->routeIs('setup') || $request->is('livewire*', 'up', 'build/*')) {
            return $next($request);
        }

        return redirect()->route('setup');
    }

    public static function isComplete(): bool
    {
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        $complete = User::query()->exists();

        if ($complete) {
            Cache::forever(self::CACHE_KEY, true);
        }

        return $complete;
    }
}
