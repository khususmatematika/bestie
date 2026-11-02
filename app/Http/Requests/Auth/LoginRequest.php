<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => 'email/NIP',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Login dengan email ATAU NIP (FR-USR-01). Akun nonaktif ditolak.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) $this->input('identifier'));
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'nip';

        if ($field === 'email') {
            $identifier = mb_strtolower($identifier);
        }

        $attempted = Auth::attempt(
            [$field => $identifier, 'password' => (string) $this->input('password'), 'is_active' => true],
            $this->boolean('remember'),
        );

        if (! $attempted) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit('login-ip:'.$this->ip(), 300);

            throw ValidationException::withMessages([
                'identifier' => 'Email/NIP atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Maksimal 5 percobaan per menit per kombinasi identitas + IP (rules.md §6.3).
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        // Batas tambahan per IP (menahan tebak banyak akun dari satu alamat): 30 gagal / 5 menit.
        if (RateLimiter::tooManyAttempts('login-ip:'.$this->ip(), 30)) {
            $wait = RateLimiter::availableIn('login-ip:'.$this->ip());

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan masuk dari perangkat ini. Coba lagi dalam {$wait} detik.",
            ]);
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('identifier')).'|'.$this->ip());
    }
}
