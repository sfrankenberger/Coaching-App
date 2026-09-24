<?php

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function (CurrentTenant $tenant) {
    return view('tenant-home', ['tenant' => $tenant->getOrFail()]);
})->name('home');
