<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('courier:admin {email} {--name=Branch Admin}', function () {
    $email = $this->argument('email');
    $name = $this->option('name');
    $password = $this->secret('Password (12+ characters, letters and numbers)');
    $validation = Validator::make(compact('email', 'name', 'password'), ['email' => 'required|email|unique:users,email', 'name' => 'required|string|max:150', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
    if ($validation->fails()) {
        foreach ($validation->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'active' => true]);
    $this->info('Administrator created.');

    return 0;
})->purpose('Create an initial administrator with a hidden password prompt');
