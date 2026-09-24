<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\KursController;
use App\Http\Controllers\ProfilController;
use App\Tenancy\Branding;
use Illuminate\Support\Facades\Route;

// PWA-Manifest je Mandant
Route::get('/manifest.webmanifest', fn (Branding $branding) => response()
    ->json($branding->manifest())
    ->header('Content-Type', 'application/manifest+json')
)->name('manifest');

// Anmelden
Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [LoginController::class, 'form'])->name('anmelden');
    Route::post('/anmelden/link', [LoginController::class, 'sendLink'])->name('anmelden.link');
    Route::post('/anmelden/passwort', [LoginController::class, 'password'])->name('anmelden.passwort');
    Route::get('/anmelden/dienst/{dienst}', [SocialController::class, 'redirect'])->name('anmelden.dienst');
    Route::match(['get', 'post'], '/anmelden/dienst/{dienst}/zurueck', [SocialController::class, 'callback'])->name('anmelden.dienst.zurueck');
});
// Der Link aus der Mail darf auch klappen, wenn schon jemand angemeldet ist (anderes Konto).
Route::get('/anmelden/{token}', [LoginController::class, 'token'])->name('anmelden.token')->where('token', '[A-Za-z0-9]{40,64}');
Route::post('/abmelden', [LoginController::class, 'logout'])->name('abmelden');

// Angemeldet, mit Mitgliedschaft im Mandanten
Route::middleware(['auth', 'membership'])->group(function () {
    Route::get('/', fn () => view('home'))->name('home');
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::post('/profil', [ProfilController::class, 'save'])->name('profil.speichern');
    Route::post('/profil/benachrichtigungen', [ProfilController::class, 'notifications'])->name('profil.benachrichtigungen');
    Route::post('/profil/passwort', [ProfilController::class, 'password'])->name('profil.passwort');

    // Kursraum
    Route::get('/kurse', [KursController::class, 'index'])->name('kurse.index');
    Route::post('/kurse/antwort', [KursController::class, 'antwort'])->name('kurse.antwort');
    Route::get('/kurse/{program:slug}', [KursController::class, 'show'])->name('kurse.show');
    Route::post('/kurse/{program:slug}/freigabe', [KursController::class, 'freigabe'])->name('kurse.freigabe');
    Route::get('/kurse/{program:slug}/schritt/{schritt}', [KursController::class, 'schritt'])->name('kurse.schritt');
    Route::get('/kurse/{program:slug}/einheit/{einheit}', [KursController::class, 'einheit'])->name('kurse.einheit');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/erledigt', [KursController::class, 'erledigt'])->name('kurse.erledigt');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/teilen', [KursController::class, 'teilen'])->name('kurse.teilen');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/notiz', [KursController::class, 'notiz'])->name('kurse.notiz');
});
