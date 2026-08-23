<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:'.implode(',', Locale::SUPPORTED)],
        ]);

        $locale = $validated['locale'];

        if ($request->user()) {
            $request->user()->forceFill(['preferred_locale' => $locale])->save();
        }

        Cookie::queue(Cookie::make(Locale::COOKIE, $locale, 60 * 24 * 365, httpOnly: false));

        return back();
    }
}
