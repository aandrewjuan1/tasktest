<?php

use App\Models\User as AppUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;
use Laravel\WorkOS\WorkOS as WorkOSFacade;
use WorkOS\UserManagement;

Route::get('login', function (AuthKitLoginRequest $request) {
    return $request->redirect();
})->middleware(['guest'])->name('login');

Route::get('authenticate', function (AuthKitAuthenticationRequest $request) {
    WorkOSFacade::configure();

    $state = json_decode(request()->query('state'), true)['state'] ?? false;
    if ($state !== session()->get('state')) {
        abort(403);
    }
    session()->forget('state');

    $authResponse = (new UserManagement)->authenticateWithCode(
        config('services.workos.client_id'),
        request()->query('code')
    );

    $workosUser = $authResponse->user;
    $email = strtolower($workosUser->email);

    if (! str_ends_with($email, '@eac.edu.ph')) {
        try {
            (new UserManagement)->deleteUser($workosUser->id);
        } catch (\Throwable $e) {
            //
        }

        return redirect('/');
    }

    $existing = AppUser::where('workos_id', $workosUser->id)->first();

    if (! $existing) {
        $existing = AppUser::create([
            'name' => trim(($workosUser->firstName ?? '').' '.($workosUser->lastName ?? '')) ?: ($workosUser->firstName ?? $email),
            'email' => $workosUser->email,
            'email_verified_at' => now(),
            'workos_id' => $workosUser->id,
            'avatar' => $workosUser->profilePictureUrl ?? '',
        ]);

        event(new Registered($existing));
    } else {
        $existing->update([
            'avatar' => $workosUser->profilePictureUrl ?? '',
        ]);
    }

    Auth::guard('web')->login($existing);

    session()->put('workos_access_token', $authResponse->access_token);
    session()->put('workos_refresh_token', $authResponse->refresh_token);
    session()->regenerate();

    return to_route('workspace.index');
})->middleware(['guest']);

Route::post('logout', function (AuthKitLogoutRequest $request) {
    return $request->logout();
})->middleware(['auth'])->name('logout');
