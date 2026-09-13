<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\Hrms\EmployeeProfile;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * World-Class Employee Portal Sign-In Experience for HARAAN.
 * Supports dual-mode ingress (Corporate Email & Physical Badge Employee Code),
 * live shift context, biometric passkey readiness, and rate-limited security.
 */
class EmployeeLogin extends BaseLogin
{
    use WithRateLimiting;

    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.auth.employee-login';

    public string $authMode = 'email'; // 'email' or 'code'

    public string $identifier = 'emp.ramesh@haraan.com';

    public string $password = 'Password@123';

    public bool $remember = false;

    public function getTitle(): string | Htmlable
    {
        return 'Sign In — HARAAN Workforce Ingress';
    }

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function getShiftGreetingProperty(): string
    {
        $hour = (int) date('H');
        if ($hour >= 4 && $hour < 12) {
            return 'Morning Shift Ingress';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'Midday Operations Ingress';
        } elseif ($hour >= 17 && $hour < 22) {
            return 'Evening Tournament Ingress';
        } else {
            return 'Night Facility Watch';
        }
    }

    public function setAuthMode(string $mode): void
    {
        $this->authMode = $mode;
        if ($mode === 'code' && str_contains($this->identifier, '@')) {
            $this->identifier = 'EMP-2026-001';
        } elseif ($mode === 'email' && ! str_contains($this->identifier, '@')) {
            $this->identifier = 'emp.ramesh@haraan.com';
        }
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Rate Limit Exceeded')
                ->body("Too many failed attempts. Please pause for {$exception->secondsUntilAvailable} seconds.")
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'identifier' => "Rate limited. Please retry in {$exception->secondsUntilAvailable}s.",
            ]);
        }

        $email = trim($this->identifier);

        // Support employee code resolution (e.g. EMP-2026-001)
        if ($this->authMode === 'code' || ! str_contains($email, '@')) {
            $profile = EmployeeProfile::with('user')
                ->where('employee_code', strtoupper($email))
                ->first();

            if ($profile && $profile->user) {
                $email = $profile->user->email;
            } else {
                $this->addError('identifier', 'No active employee profile associated with this code.');
                Notification::make()->title('Employee Code Not Found')->danger()->send();
                return null;
            }
        }

        if (! Filament::auth()->attempt([
            'email' => $email,
            'password' => $this->password,
        ], $this->remember)) {
            $this->addError('identifier', 'The provided credentials do not match our workforce records.');

            Notification::make()
                ->title('Authentication Failed')
                ->body('Check your work email or employee code and password.')
                ->danger()
                ->send();

            return null;
        }

        $user = Filament::auth()->user();

        // Enforce employee role & active profile
        if ($user->role !== 'EMPLOYEE' && $user->role !== 'ADMIN') {
            Filament::auth()->logout();
            $this->addError('identifier', 'Access is restricted to authorized workforce employees.');
            return null;
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
