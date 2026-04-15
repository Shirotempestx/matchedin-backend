<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['fr', 'en'];
        $locale = null;

        $user = $request->user();
        if ($user && in_array($user->preferred_language, $supported, true)) {
            $locale = $user->preferred_language;
        }

        if (!$locale) {
            $headerLocale = $request->header('X-Locale') ?: $request->getPreferredLanguage($supported);
            $shortLocale = strtolower(substr((string) $headerLocale, 0, 2));
            if (in_array($shortLocale, $supported, true)) {
                $locale = $shortLocale;
            }
        }

        App::setLocale($locale ?: config('app.locale'));

        return $next($request);
    }
}
