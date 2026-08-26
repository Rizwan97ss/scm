<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Mfa\MfaChallengeService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accepts either an email (staff/parent accounts) or a username (student
            // accounts, which are often generated rather than a real inbox).
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{mfa_required: bool, challenge_token: string|null} 'mfa_required' true means
     * no session was established — a confirmed MFA account needs a second
     * request to /auth/mfa/verify-challenge (see MfaChallengeService) before
     * Auth::login() actually runs.
     */
    public function authenticate(): array
    {
        $this->ensureIsNotRateLimited();

        $identifier = $this->string('email');
        $user = User::query()->where('email', $identifier)->orWhere('username', $identifier)->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact your administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($user->hasMfaConfirmed()) {
            $token = app(MfaChallengeService::class)->issueChallenge($user, 'web', $this->boolean('remember'));

            return ['mfa_required' => true, 'challenge_token' => $token];
        }

        Auth::login($user, $this->boolean('remember'));

        return ['mfa_required' => false, 'challenge_token' => null];
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
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
