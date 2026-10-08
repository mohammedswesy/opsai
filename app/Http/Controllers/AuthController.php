<?php

namespace App\Http\Controllers;

use App\Actions\RegisterOrganization;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function createLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->safe()->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function createRegister(): Response
    {
        return Inertia::render('Auth/Register', [
            'countries' => config('opsai.countries'),
            'currencies' => config('opsai.currencies'),
            'locales' => config('opsai.locales'),
        ]);
    }

    public function register(RegisterRequest $request, RegisterOrganization $action): RedirectResponse
    {
        $result = $action->handle($request->validated());

        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->put('current_organization_id', $result['organization']->id);
        $request->session()->put('locale', $result['organization']->locale);

        return redirect()->route('dashboard');
    }

    public function createForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('If an account exists for that email, a reset link has been sent.'));
    }

    public function createResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
