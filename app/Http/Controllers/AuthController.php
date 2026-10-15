<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(Request $request): View
    {
        $data = $request->validate(['side' => 'nullable|in:ygn,sg']);
        $side = $data['side'] ?? $request->old('side');

        return view('auth.login', ['loginBranch' => $side ? Branch::where('code', strtoupper($side))->firstOrFail() : null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'side' => 'nullable|in:ygn,sg']);
        $branch = ! empty($data['side']) ? Branch::where('code', strtoupper($data['side']))->firstOrFail() : null;
        if (! Auth::attemptWhen(['email' => $data['email'], 'password' => $data['password'], 'active' => true], fn (User $user): bool => $branch === null || $user->isAdmin() || $user->branch_id === $branch->id)) {
            return back()->withErrors(['email' => $branch ? __('Check your email, password and selected branch.') : __('The email or password is incorrect.')])->withInput($request->only('email', 'side'));
        }
        $request->session()->regenerate();

        if ($branch) {
            $request->session()->forget('url.intended');

            return redirect()->route('dashboard.branch', ['side' => strtolower($branch->code)]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = app()->getLocale();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('locale', $locale);

        return redirect()->route('login');
    }
}
