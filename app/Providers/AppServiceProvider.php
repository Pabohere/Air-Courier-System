<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->active && $user->isAdmin());
        View::composer(['layouts.app', 'auth.login', 'shipments.receipt', 'dispatches.manifest'], fn ($view) => $view->with('company', CompanySetting::first()));
    }
}
