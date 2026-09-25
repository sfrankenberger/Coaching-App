<?php

use App\Http\Controllers\AufgabenController;
use App\Http\Controllers\Auth\BridgeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\BuchenController;
use App\Http\Controllers\FragenController;
use App\Http\Controllers\GespraechController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Hooks\WooCommerceController;
use App\Http\Controllers\ImpulseController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KommentarController;
use App\Http\Controllers\KursController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MedienController;
use App\Http\Controllers\MerklisteController;
use App\Http\Controllers\NotizenController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReflexionController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TermineController;
use App\Http\Controllers\ThemenController;
use App\Http\Controllers\UebungController;
use App\Http\Controllers\WillkommenController;
use App\Tenancy\Branding;
use Illuminate\Support\Facades\Route;

// PWA-Manifest je Mandant
Route::get('/manifest.webmanifest', fn (Branding $branding) => response()
    ->json($branding->manifest())
    ->header('Content-Type', 'application/manifest+json')
)->name('manifest');

// Eingehende Webhooks (ohne Anmeldung, je Mandant ueber die Domain)
Route::post('/hooks/telegram/{secret}', [TelegramController::class, 'webhook'])->name('hooks.telegram');
Route::post('/hooks/woocommerce', WooCommerceController::class)->name('hooks.woocommerce');

// Bruecke aus dem alten Mitgliederbereich (signierter Link, 60 Sekunden, einmalig)
Route::get('/sso', BridgeController::class)->name('sso');

// Kalender-Abo (ohne Anmeldung, Schluessel je Person)
Route::get('/kalender/{token}.ics', [KalenderController::class, 'abo'])->name('kalender.abo')->where('token', '[A-Za-z0-9]{32,64}');

// Anmelden
Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [LoginController::class, 'form'])->name('anmelden');
    Route::post('/anmelden/link', [LoginController::class, 'sendLink'])->name('anmelden.link');
    Route::post('/anmelden/passwort', [LoginController::class, 'password'])->name('anmelden.passwort');
    Route::get('/anmelden/dienst/{dienst}', [SocialController::class, 'redirect'])->name('anmelden.dienst');
    Route::match(['get', 'post'], '/anmelden/dienst/{dienst}/zurueck', [SocialController::class, 'callback'])->name('anmelden.dienst.zurueck');
    Route::post('/passkeys/anmelden/optionen', [PasskeyController::class, 'loginOptions'])->name('passkeys.anmelden.optionen');
    Route::post('/passkeys/anmelden', [PasskeyController::class, 'login'])->name('passkeys.anmelden');
});
// Der Link aus der Mail darf auch klappen, wenn schon jemand angemeldet ist (anderes Konto).
Route::get('/anmelden/{token}', [LoginController::class, 'token'])->name('anmelden.token')->where('token', '[A-Za-z0-9]{40,64}');
Route::post('/abmelden', [LoginController::class, 'logout'])->name('abmelden');

// Angemeldet, mit Mitgliedschaft im Mandanten
Route::middleware(['auth', 'membership'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/neu/gesehen', [HomeController::class, 'gesehen'])->name('neu.gesehen');
    Route::post('/medien/position', [MedienController::class, 'position'])->middleware('throttle:120,1')->name('medien.position');
    Route::get('/willkommen', [WillkommenController::class, 'index'])->name('willkommen');
    Route::post('/willkommen', [WillkommenController::class, 'fertig'])->name('willkommen.fertig');
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::post('/profil', [ProfilController::class, 'save'])->name('profil.speichern');
    Route::post('/profil/benachrichtigungen', [ProfilController::class, 'notifications'])->name('profil.benachrichtigungen');
    Route::post('/profil/passwort', [ProfilController::class, 'password'])->name('profil.passwort');
    Route::post('/profil/hilfe', [ProfilController::class, 'hilfe'])->middleware('throttle:5,10')->name('profil.hilfe');
    Route::post('/passkeys/anlegen/optionen', [PasskeyController::class, 'registerOptions'])->name('passkeys.anlegen.optionen');
    Route::post('/passkeys/anlegen', [PasskeyController::class, 'register'])->name('passkeys.anlegen');
    Route::delete('/passkeys/{id}', [PasskeyController::class, 'destroy'])->name('passkeys.loeschen');
    Route::get('/push/schluessel', [PushController::class, 'schluessel'])->name('push.schluessel');
    Route::post('/push/abo', [PushController::class, 'abo'])->name('push.abo');
    Route::delete('/push/abo', [PushController::class, 'weg'])->name('push.weg');
    Route::post('/telegram/verbinden', [TelegramController::class, 'verbinden'])->name('telegram.verbinden');
    Route::post('/telegram/trennen', [TelegramController::class, 'trennen'])->name('telegram.trennen');

    // Kursraum
    Route::get('/kurse', [KursController::class, 'index'])->name('kurse.index');
    Route::post('/kurse/antwort', [KursController::class, 'antwort'])->name('kurse.antwort');
    Route::post('/kurse/antwort/aufnahme', [UebungController::class, 'aufnahme'])->middleware('throttle:20,10')->name('uebung.aufnahme');
    Route::get('/kurse/antwort/{antwort}/aufnahme', [UebungController::class, 'hoeren'])->name('uebung.aufnahme.hoeren');
    Route::post('/kurse/uebung/{uebung}/praxis', [UebungController::class, 'praxis'])->name('uebung.praxis');
    Route::get('/kurse/{program:slug}', [KursController::class, 'show'])->name('kurse.show');
    Route::post('/kurse/{program:slug}/freigabe', [KursController::class, 'freigabe'])->name('kurse.freigabe');
    Route::get('/kurse/{program:slug}/schritt/{schritt}', [KursController::class, 'schritt'])->name('kurse.schritt');
    Route::get('/kurse/{program:slug}/einheit/{einheit}', [KursController::class, 'einheit'])->name('kurse.einheit');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/erledigt', [KursController::class, 'erledigt'])->name('kurse.erledigt');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/teilen', [KursController::class, 'teilen'])->name('kurse.teilen');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/notiz', [KursController::class, 'notiz'])->name('kurse.notiz');

    // Termine
    Route::get('/termine', [TermineController::class, 'index'])->name('termine.index');
    Route::get('/buchen', [BuchenController::class, 'index'])->name('buchen.index');
    Route::get('/buchen/{art}', [BuchenController::class, 'zeiten'])->name('buchen.zeiten');
    Route::post('/buchen/{art}', [BuchenController::class, 'store'])->middleware('throttle:10,1')->name('buchen.store');
    Route::post('/buchungen/{booking}/absagen', [BuchenController::class, 'absagen'])->name('buchen.absagen');
    Route::get('/termine/{termin}', [TermineController::class, 'show'])->name('termine.show');
    Route::post('/termine/{termin}/dabei', [TermineController::class, 'dabei'])->name('termine.dabei');
    Route::post('/termine/{termin}/gesehen', [TermineController::class, 'gesehen'])->name('termine.gesehen');
    Route::post('/termine/{termin}/aufgabe', [TermineController::class, 'aufgabe'])->name('termine.aufgabe');
    Route::get('/termine/{termin}/kalender.ics', [KalenderController::class, 'termin'])->name('termine.ics');

    // Material und Merkliste
    Route::get('/material', [MaterialController::class, 'index'])->name('material.index');
    Route::get('/material/{material}', [MaterialController::class, 'show'])->name('material.show');
    Route::get('/material/{material}/datei', [MaterialController::class, 'datei'])->name('material.datei');
    Route::post('/merken', [MaterialController::class, 'merken'])->name('merken');
    Route::get('/merkliste', [MerklisteController::class, 'index'])->name('merkliste');

    // Impulse, Podcast, Themenfinder
    Route::get('/impulse', [ImpulseController::class, 'index'])->name('impulse.index');
    Route::get('/impulse/folge/{folge}', [ImpulseController::class, 'folge'])->name('impulse.folge');
    Route::get('/impulse/{post:slug}', [ImpulseController::class, 'show'])->name('impulse.show');
    Route::get('/themen', [ThemenController::class, 'index'])->name('themen.index');
    Route::get('/themen/{thema:slug}', [ThemenController::class, 'show'])->name('themen.show');

    // Mein Journal: Aufgaben, Notizen, Reflexion
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::get('/aufgaben', [AufgabenController::class, 'index'])->name('aufgaben.index');
    Route::post('/aufgaben', [AufgabenController::class, 'store'])->name('aufgaben.store');
    Route::post('/aufgaben/{aufgabe}', [AufgabenController::class, 'update'])->name('aufgaben.update');
    Route::post('/aufgaben/{aufgabe}/haken', [AufgabenController::class, 'haken'])->name('aufgaben.haken');
    Route::post('/aufgaben/{aufgabe}/tag', [AufgabenController::class, 'tag'])->name('aufgaben.tag');
    Route::delete('/aufgaben/{aufgabe}', [AufgabenController::class, 'destroy'])->name('aufgaben.destroy');
    Route::get('/notizen', [NotizenController::class, 'index'])->name('notizen.index');
    Route::post('/notizen', [NotizenController::class, 'store'])->name('notizen.store');
    Route::post('/notizen/{notiz}', [NotizenController::class, 'update'])->name('notizen.update');
    Route::delete('/notizen/{notiz}', [NotizenController::class, 'destroy'])->name('notizen.destroy');
    Route::get('/reflexion', [ReflexionController::class, 'index'])->name('reflexion.index');
    Route::post('/reflexion', [ReflexionController::class, 'store'])->name('reflexion.store');
    Route::post('/reflexion/{reflexion}/nachtrag', [ReflexionController::class, 'nachtrag'])->name('reflexion.nachtrag');
    Route::post('/reflexion/{reflexion}/teilen', [ReflexionController::class, 'teilen'])->name('reflexion.teilen');
    Route::delete('/reflexion/{reflexion}', [ReflexionController::class, 'destroy'])->name('reflexion.destroy');

    // Gespraeche (1:1 und Gruppe)
    Route::get('/gespraech', [GespraechController::class, 'index'])->name('gespraech.index');
    Route::get('/gespraech/{gespraech}', [GespraechController::class, 'show'])->name('gespraech.show');
    Route::post('/gespraech/{gespraech}/senden', [GespraechController::class, 'senden'])->name('gespraech.senden');
    Route::get('/gespraech/{gespraech}/neu', [GespraechController::class, 'neu'])->name('gespraech.neu');
    Route::post('/gespraech/{gespraech}/gelesen', [GespraechController::class, 'gelesen'])->name('gespraech.gelesen');
    Route::post('/nachricht/{nachricht}/termin', [GespraechController::class, 'termin'])->middleware('throttle:10,1')->name('nachricht.termin');
    Route::post('/nachricht/{nachricht}/reaktion', [GespraechController::class, 'reaktion'])->name('nachricht.reaktion');
    Route::get('/nachricht/{nachricht}/{art}', [GespraechController::class, 'datei'])->name('nachricht.datei')->where('art', 'audio|datei');
    Route::get('/kurse/{program:slug}/austausch', [GespraechController::class, 'gruppe'])->name('kurse.austausch');

    // Fragen an die Coachin im Kursraum
    Route::get('/kurse/{program:slug}/fragen', [FragenController::class, 'index'])->name('kurse.fragen');
    Route::post('/kurse/{program:slug}/fragen', [FragenController::class, 'store'])->middleware('throttle:20,10')->name('kurse.fragen.store');
    Route::get('/fragen/{frage}', [FragenController::class, 'show'])->name('fragen.show');
    Route::post('/fragen/{frage}/antworten', [FragenController::class, 'antworten'])->middleware('throttle:30,10')->name('fragen.antworten');
    Route::post('/fragen/{frage}/status', [FragenController::class, 'status'])->name('fragen.status');
    Route::post('/fragen/{frage}/call', [FragenController::class, 'call'])->name('fragen.call');
    Route::delete('/fragen/{frage}', [FragenController::class, 'destroy'])->name('fragen.destroy');
    Route::delete('/antworten/{antwort}', [FragenController::class, 'antwortLoeschen'])->name('fragen.antwort.loeschen');
    Route::post('/kommentar', [KommentarController::class, 'store'])->middleware('throttle:30,1')->name('kommentar.store');
    Route::delete('/kommentar/{kommentar}', [KommentarController::class, 'destroy'])->name('kommentar.destroy');
});
