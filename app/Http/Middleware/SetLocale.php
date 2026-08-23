<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve UI locale for guests and authenticated users (ADR-021 / P0-8).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        if (! $request->cookies->has(Locale::COOKIE) || $request->cookie(Locale::COOKIE) !== $locale) {
            $response->headers->setCookie(
                Cookie::make(Locale::COOKIE, $locale, 60 * 24 * 365, httpOnly: false)
            );
        }

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        $user = $request->user();
        $cookieLocale = Locale::isSupported($request->cookie(Locale::COOKIE))
            ? $request->cookie(Locale::COOKIE)
            : null;

        if ($user !== null) {
            if (Locale::isSupported($user->preferred_locale)) {
                return $user->preferred_locale;
            }

            if ($cookieLocale !== null) {
                $user->forceFill(['preferred_locale' => $cookieLocale])->save();

                return $cookieLocale;
            }

            $fromHeader = Locale::fromAcceptLanguage($request->header('Accept-Language'));
            $locale = $fromHeader ?? Locale::DEFAULT;
            $user->forceFill(['preferred_locale' => $locale])->save();

            return $locale;
        }

        if ($cookieLocale !== null) {
            return $cookieLocale;
        }

        return Locale::fromAcceptLanguage($request->header('Accept-Language')) ?? Locale::DEFAULT;
    }
}
