<?php

use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('tasks', 'tasks.index')->name('tasks.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
