<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function loginForm(Request $request)
    {
        return view('auth.login', ['redirectTo' => $this->allowedRedirect($request)]);
    }

    public function registerForm(Request $request)
    {
        return view('auth.register', ['redirectTo' => $this->allowedRedirect($request)]);
    }

    public function professionalLoginForm()
    {
        return view('auth.login-professional');
    }

    public function adminLoginForm()
    {
        return view('auth.login-admin');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'redirect_to' => ['nullable', Rule::in(['checkout'])],
        ]);
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if ($request->user()->blocked) {
            $this->closeSession($request);

            return back()->withErrors(['email' => 'Ce compte est bloqué. Contactez le support.']);
        }

        if ($request->user()->role === 'reseller' || $request->user()->resellerRequest()->exists()) {
            $this->closeSession($request);

            return back()->withErrors(['email' => 'Ce compte utilise l’accès professionnel. Connectez-vous depuis le portail AchatHub Pro.'])->onlyInput('email');
        }

        if ($request->user()->role === 'admin') {
            return redirect()->route('admin.index');
        }

        if (($data['redirect_to'] ?? null) === 'checkout') {
            return redirect()->route('checkout.index');
        }

        return redirect()->intended(route('account.index'));
    }

    public function professionalLogin(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants professionnels incorrects.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        if ($user->blocked || ! $user->resellerRequest()->exists()) {
            $this->closeSession($request);

            return back()->withErrors(['email' => 'Ces identifiants ne correspondent pas à un compte professionnel actif.'])->onlyInput('email');
        }

        if ($user->role === 'reseller' && $user->resellerRequest()->where('status', 'Approuvée')->exists()) {
            return redirect()->route('pro.index');
        }

        return redirect()->route('reseller.dashboard')->with('success', 'Votre dossier professionnel est encore en cours de validation.');
    }

    public function adminLogin(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt([...$credentials, 'role' => 'admin'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Identifiants administrateur incorrects.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        if ($request->user()->blocked) {
            $this->closeSession($request);

            return back()->withErrors(['email' => 'Ce compte administrateur est bloqué.']);
        }

        return redirect()->intended(route('admin.index'));
    }

    public function register(Request $request, TransactionalMailer $mailer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
            'terms' => ['accepted'],
            'redirect_to' => ['nullable', Rule::in(['checkout'])],
        ]);
        $redirectTo = $data['redirect_to'] ?? null;
        unset($data['terms'], $data['redirect_to']);
        $user = User::create([...$data, 'password' => Hash::make($data['password']), 'role' => 'customer', 'terms_accepted_at' => now(), 'privacy_version' => '2026-08-03']);
        Auth::login($user);
        $request->session()->regenerate();
        $mailer->verification($user);
        $mailer->customerCreated($user);

        $target = $redirectTo === 'checkout' ? 'checkout.index' : 'account.index';

        return redirect()->route($target)->with('success', 'Votre compte AchatHub est créé. Consultez votre e-mail pour confirmer votre adresse.');
    }

    public function forgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, TransactionalMailer $mailer)
    {
        $request->validate(['email' => ['required', 'email']]);

        if (! $mailer->isConfigured()) {
            throw ValidationException::withMessages([
                'email' => 'L’envoi d’e-mails est temporairement indisponible. Contactez le support AchatHub.',
            ]);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return back()->with('success', 'Si ce compte existe, un lien sécurisé vient d’être envoyé.');
    }

    public function resetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->string('email')]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Votre mot de passe est modifié. Vous pouvez vous connecter.');
    }

    public function logout(Request $request)
    {
        $this->closeSession($request);

        return redirect()->route('home');
    }

    private function closeSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function allowedRedirect(Request $request): ?string
    {
        return $request->string('redirect_to')->toString() === 'checkout' ? 'checkout' : null;
    }
}
