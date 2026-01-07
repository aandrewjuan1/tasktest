<?php

use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Volt\Volt;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Volt::route('/', 'workspace.index')->name('workspace.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
