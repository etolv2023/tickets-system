<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        // Never "remember": the point of the 120-minute idle window is that an
        // unattended session dies (F00.2).
        if (! Auth::attempt($request->validated())) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::user();

        // Checked after the password, so a wrong password on a disabled account
        // still reads "wrong password" — otherwise this leaks which accounts exist.
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages(['email' => __('auth.inactive')]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // Kills any session-fixation token the visitor arrived with (F00.2).
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($request)),
            ]),
        ]);
    }

    /**
     * Keyed by email AND ip. Keying on ip alone — which is what a bare
     * throttle:5,1 route middleware does — would let five failed logins lock
     * out an entire office sharing one address.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')) . '|' . $request->ip());
    }
}
