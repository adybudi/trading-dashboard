<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))->with('success', 'Selamat datang kembali, ' . Auth::user()->name);
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi yang dimasukkan salah.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Automatically create a default trading account for new trader
        Account::create([
            'user_id' => $user->id,
            'name' => 'Main Trading Account',
            'broker' => 'Primary Broker',
            'account_type' => 'forex',
            'currency' => 'USD',
            'initial_balance' => 10000.00,
            'current_balance' => 10000.00,
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun trader berhasil dibuat. Selamat datang di ALPHAMACHINE!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar (logged out).');
    }

    public function demoLogin(Request $request)
    {
        // NEVER fall back to User::first() here — on a fresh/local install
        // with only one real user, that would silently log the "demo"
        // button into that person's actual account instead of a dedicated
        // demo user. If no trader@example.com demo account exists yet, the
        // block below creates one properly.
        $demoUser = User::where('email', 'trader@example.com')->first();

        if (!$demoUser) {
            $demoUser = User::create([
                'name' => 'Demo Trader',
                'email' => 'trader@example.com',
                'password' => Hash::make('password'),
            ]);

            Account::create([
                'user_id' => $demoUser->id,
                'name' => 'Demo Binance Futures',
                'broker' => 'Binance',
                'account_type' => 'crypto',
                'currency' => 'USD',
                'initial_balance' => 10000.00,
                'current_balance' => 10000.00,
                'is_active' => true,
            ]);
        }

        Auth::login($demoUser);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Masuk sebagai Demo Trader: ' . $demoUser->name);
    }
}
