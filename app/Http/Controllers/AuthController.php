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
        $request->validate(['side' => 'nullable|in:ygn,sg']);

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'side' => 'nullable|in:ygn,sg']);
        $branch = ! empty($data['side']) ? Branch::where('code', strtoupper($data['side']))->firstOrFail() : null;
        if (! Auth::attemptWhen(['email' => $data['email'], 'password' => $data['password'], 'active' => true], fn (User $user): bool => $branch === null || $user->isAdmin() || $user->branch_id === $branch->id)) {
            return back()->withErrors(['email' => $branch ? __('Check your email, password and selected branch.') : __('The email or password is incorrect.')])->withInput($request->only('email', 'side'));
        }
        $request->session()->regenerate();
        $request->session()->put('dashboard_branch_code', ($branch ?? Auth::user()->branch)?->code ?? 'YGN');

        if ($branch) {
            $request->session()->put('dashboard_branch_code', $branch->code);
            $request->session()->forget('url.intended');

            return redirect()->route('dashboard');
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
