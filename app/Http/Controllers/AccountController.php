<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    protected function getUser()
    {
        return Auth::user() ?? User::first();
    }

    public function index()
    {
        $user = $this->getUser();
        $accounts = $user->accounts()->withCount('trades')->get();
        return view('accounts.index', compact('accounts', 'user'));
    }

    public function store(Request $request)
    {
        $user = $this->getUser();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'broker' => 'nullable|string|max:100',
            'account_type' => 'required|in:forex,crypto,stock,futures',
            'currency' => 'required|string|max:10',
            'initial_balance' => 'required|numeric|min:0',
        ]);

        $account = $user->accounts()->create([
            'name' => $validated['name'],
            'broker' => $validated['broker'],
            'account_type' => $validated['account_type'],
            'currency' => strtoupper($validated['currency']),
            'initial_balance' => $validated['initial_balance'],
            'current_balance' => $validated['initial_balance'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Akun trading '{$account->name}' berhasil ditambahkan.");
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'broker' => 'nullable|string|max:100',
            'account_type' => 'required|in:forex,crypto,stock,futures',
            'currency' => 'required|string|max:10',
            'initial_balance' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $account->update($validated);
        $account->recalculateBalance();

        return redirect()->back()->with('success', "Akun trading '{$account->name}' berhasil diperbarui.");
    }

    public function destroy(Account $account)
    {
        $name = $account->name;
        $account->delete();

        return redirect()->back()->with('success', "Akun '{$name}' dan riwayat transaksinya berhasil dihapus.");
    }
}
