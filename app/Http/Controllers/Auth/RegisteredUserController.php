<?php

namespace App\Http\Controllers\Auth;

use App\Cart\CartMergeResult;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use App\Support\Locale;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CartService $carts): RedirectResponse
    {
        $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'phone' => ['required', 'string', 'max:20', 'unique:'.User::class, 'regex:/^\+?[0-9]{8,15}$/'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'phone.regex' => __('Enter a valid phone number.'),
            ],
        );

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'preferred_locale' => Locale::sanitize(app()->getLocale()),
        ]);

        $user->assignRole(Role::CUSTOMER);

        event(new Registered($user));

        Auth::login($user);

        $merge = $carts->mergeGuestCart($user);
        $request->session()->flash(CartMergeResult::FLASH_KEY, $merge->toFlashPayload());

        return redirect(route(
            $merge->isEmpty() ? 'dashboard' : 'cart.show',
            absolute: false,
        ));
    }
}
