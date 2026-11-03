<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authenticate with an email address or phone number.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $field = filter_var($this->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$field => $this->input('login'), 'password' => $this->input('password'), 'status' => 'active'], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey()), 'minutes' => 1]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login')).'|'.$this->ip());
    }
}
