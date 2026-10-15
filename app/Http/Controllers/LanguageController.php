<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'my'])], 'return_to' => 'nullable|string|max:2048']);
        $request->session()->put('locale', $data['locale']);
        Cookie::queue(cookie('courier_locale', $data['locale'], 60 * 24 * 180, '/', null, $request->isSecure(), true, false, 'lax'));
        $path = $data['return_to'] ?? '/';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, chr(92)) || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            $path = $request->user() ? '/' : '/login';
        }

        return redirect()->to($path);
    }
}
