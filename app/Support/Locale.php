<?php

namespace App\Support;

final class Locale
{
    public const COOKIE = 'locale';

    public const DEFAULT = 'ar';

    /**
     * @var list<string>
     */
    public const SUPPORTED = ['ar', 'en'];

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function sanitize(?string $locale): string
    {
        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    public static function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

            if (self::isSupported($code)) {
                return $code;
            }
        }

        return null;
    }

    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }
}
