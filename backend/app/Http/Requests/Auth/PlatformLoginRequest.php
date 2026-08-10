<?php

namespace App\Http\Requests\Auth;

use App\Models\Platform\PlatformUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin's own login, separate from the tenant LoginRequest -- it
 * queries PlatformUser (landlord connection) rather than the current
 * tenant's User table, and logs in on the 'platform' guard specifically.
 */
class PlatformLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = PlatformUser::query()->where('email', $this->string('email'))->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        Auth::guard('platform')->login($user, $this->boolean('remember'));

        // Authenticate middleware does this automatically for subsequent
        // requests (Illuminate\Auth\Middleware\Authenticate::authenticate())
        // -- replicated here so $request->user()/Gate resolve correctly
        // for THIS request's own response too, the same way the tenant
        // LoginController's Auth::login() (implicitly on the default 'web'
        // guard) already does without needing this extra call.
        Auth::shouldUse('platform');

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'platform|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
