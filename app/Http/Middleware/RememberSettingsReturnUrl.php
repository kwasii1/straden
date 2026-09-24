<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the in-app page the user came from so the settings layout can
 * offer a "back" link, even after moving between settings sub-pages.
 */
class RememberSettingsReturnUrl
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = url()->previous();

        if (
            $previous !== $request->url()
            && Str::startsWith($previous, url('/'))
            && ! Str::startsWith($previous, url('settings'))
        ) {
            $request->session()->put('settings.return_to', $previous);
        }

        return $next($request);
    }
}
