<?php

use App\Http\Controllers\AboController;
use App\Http\Controllers\AlsController;
use App\Http\Controllers\AltlinkController;
use App\Http\Controllers\AngeboteController;
use App\Http\Controllers\AnhaengeController;
use App\Http\Controllers\AnsichtController;
use App\Http\Controllers\ArbeitsbuchPdfController;
use App\Http\Controllers\AssistentChatController;
use App\Http\Controllers\AssistentController;
use App\Http\Controllers\AufgabenController;
use App\Http\Controllers\Auth\BridgeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\BuchenController;
use App\Http\Controllers\BuchhaltungController;
use App\Http\Controllers\CoacheesController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\EinheitController;
use App\Http\Controllers\ElementController;
use App\Http\Controllers\FragenController;
use App\Http\Controllers\GastBuchenController;
use App\Http\Controllers\GespraechController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Hooks\WooCommerceController;
use App\Http\Controllers\ImpulseController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KaufenController;
use App\Http\Controllers\KommentarController;
use App\Http\Controllers\KursController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MedienController;
use App\Http\Controllers\MerklisteController;
use App\Http\Controllers\MitteilungenController;
use App\Http\Controllers\NachschlagenController;
use App\Http\Controllers\NewsletterBildController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NewsletterVorschauController;
use App\Http\Controllers\NotizenController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ProjekteController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReaktionController;
use App\Http\Controllers\ReflexionController;
use App\Http\Controllers\StreckeController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SucheController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TermineController;
use App\Http\Controllers\ThemenController;
use App\Http\Controllers\UebungController;
use App\Http\Controllers\WerkzeugeController;
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
Route::post('/hooks/stripe', [StripeController::class, 'webhook'])->name('hooks.stripe');
Route::post('/hooks/woocommerce', WooCommerceController::class)->name('hooks.woocommerce');

// Bruecke aus dem alten Mitgliederbereich (signierter Link, 60 Sekunden, einmalig)
Route::get('/sso', BridgeController::class)->name('sso');
// Newsletter: Anmeldung (Website-Formular), Bestaetigung, Abmeldung, Zaehlung, Webversion (ohne Anmeldung)
Route::get('/newsletter/anmelden', [NewsletterController::class, 'anmeldenForm'])->name('newsletter.anmelden');
Route::post('/newsletter/anmelden', [NewsletterController::class, 'anmelden'])->middleware('throttle:10,10')->name('newsletter.anmelden.store');
Route::get('/newsletter/bestaetigen/{kontakt}', [NewsletterController::class, 'bestaetigen'])->middleware('signed')->name('newsletter.bestaetigen');
Route::get('/n/bild/{datei}', NewsletterBildController::class)->name('newsletter.bild');
Route::get('/n/abmelden', [NewsletterController::class, 'abmeldenForm'])->name('newsletter.abmelden.form');
Route::post('/n/abmelden', [NewsletterController::class, 'abmeldeLink'])->middleware('throttle:10,10')->name('newsletter.abmelden.suchen');
Route::match(['get', 'post'], '/n/abmelden/{token}', [NewsletterController::class, 'abmelden'])->name('newsletter.abmelden')->where('token', '[A-Za-z0-9]{40}');
Route::post('/n/dabei/{token}', [NewsletterController::class, 'wiederAnmelden'])->name('newsletter.dabei')->where('token', '[A-Za-z0-9]{40}');
Route::get('/n/o/{token}.gif', [NewsletterController::class, 'oeffnen'])->name('newsletter.oeffnen')->where('token', '[A-Za-z0-9]{40}');
Route::get('/n/k/{token}', [NewsletterController::class, 'klick'])->name('newsletter.klick')->where('token', '[A-Za-z0-9]{40}');
Route::get('/n/w/{token}', [NewsletterController::class, 'web'])->name('newsletter.web')->where('token', '[A-Za-z0-9]{40}');
// Alte Adressen aus dem WordPress-Mitgliederbereich (nach dem Umschalten per 301 hierher geleitet)
Route::get('/mitgliederbereich/{pfad?}', AltlinkController::class)->where('pfad', '.*')->name('altlink');

// Logo und App-Icon aus den Einstellungen (ohne Anmeldung, gecacht)
Route::get('/branding/{datei}', BrandingController::class)->name('branding.datei');
// Kalender-Abo (ohne Anmeldung, Schluessel je Person)
Route::get('/kalender/{token}.ics', [KalenderController::class, 'abo'])->name('kalender.abo')->where('token', '[A-Za-z0-9]{32,64}');
Route::get('/kalender/{token}/{program:slug}.ics', [KalenderController::class, 'abo'])->name('kalender.kurs')->where('token', '[A-Za-z0-9]{32,64}');

// Klarheitsgespraech fuer Gaeste, ohne Anmeldung (Website verweist hierher)
Route::get('/buchen/gast/{art}', [GastBuchenController::class, 'zeiten'])->name('buchen.gast');
Route::post('/buchen/gast/{art}', [GastBuchenController::class, 'store'])->middleware('throttle:5,10')->name('buchen.gast.store');
Route::get('/buchen/gast/{art}/danke', [GastBuchenController::class, 'danke'])->name('buchen.gast.danke');

// Kasse: Kauflink fuer Angebote, offen fuer alle (angemeldet mit einem Klick, sonst mit Name und Mail)
Route::get('/kaufen/{angebot}', [KaufenController::class, 'show'])->name('kaufen');
Route::post('/kaufen/{angebot}', [KaufenController::class, 'store'])->middleware('throttle:6,10')->name('kaufen.store');
Route::get('/kaufen/{angebot}/danke', [KaufenController::class, 'danke'])->name('kaufen.danke');
Route::get('/strecke/{program}/{user}/stopp', [StreckeController::class, 'stopp'])->middleware('signed')->name('strecke.stopp');

// Anmelden
Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [LoginController::class, 'form'])->name('anmelden');
    Route::post('/anmelden/link', [LoginController::class, 'sendLink'])->name('anmelden.link');
    Route::post('/anmelden/passwort', [LoginController::class, 'password'])->name('anmelden.passwort');
    Route::get('/anmelden/dienst/{dienst}', [SocialController::class, 'redirect'])->middleware('throttle:20,1')->name('anmelden.dienst');
    Route::post('/passkeys/anmelden/optionen', [PasskeyController::class, 'loginOptions'])->middleware('throttle:30,1')->name('passkeys.anmelden.optionen');
    Route::post('/passkeys/anmelden', [PasskeyController::class, 'login'])->middleware('throttle:10,1')->name('passkeys.anmelden');
});
// Rueckkehr von Google/Apple: auch angemeldet, denn aus dem Profil laesst sich ein Dienst verknuepfen.
Route::match(['get', 'post'], '/anmelden/dienst/{dienst}/zurueck', [SocialController::class, 'callback'])->name('anmelden.dienst.zurueck');
// Der Link aus der Mail darf auch klappen, wenn schon jemand angemeldet ist (anderes Konto).
Route::get('/anmelden/{token}', [LoginController::class, 'token'])->middleware('throttle:20,1')->name('anmelden.token')->where('token', '[A-Za-z0-9]{40,64}');
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
    Route::post('/profil/schluessel', [ProfilController::class, 'schluessel'])->name('profil.schluessel');
    Route::post('/profil/foto', [AvatarController::class, 'speichern'])->name('profil.foto');
    Route::delete('/profil/foto', [AvatarController::class, 'loeschen'])->name('profil.foto.loeschen');
    Route::get('/avatar/{user}', [AvatarController::class, 'show'])->name('avatar');
    Route::get('/angebote', [AngeboteController::class, 'index'])->name('angebote');
    Route::get('/abo/portal', [AboController::class, 'portal'])->name('abo.portal');
    Route::get('/rechnungen/{rechnung}/pdf', [BuchhaltungController::class, 'pdf'])->name('rechnung.pdf');
    Route::get('/buchhaltung/bexio/start', [BuchhaltungController::class, 'bexioStart'])->name('buchhaltung.bexio.start');
    Route::get('/buchhaltung/bexio/rueckkehr', [BuchhaltungController::class, 'bexioRueckkehr'])->name('buchhaltung.bexio.rueckkehr');
    Route::delete('/profil/schluessel/{id}', [ProfilController::class, 'schluesselLoeschen'])->name('profil.schluessel.loeschen');
    Route::get('/profil/dienst/{dienst}/verknuepfen', [SocialController::class, 'verknuepfen'])->name('profil.dienst.verknuepfen');
    Route::delete('/profil/dienst/{dienst}', [SocialController::class, 'trennen'])->name('profil.dienst.trennen');
    Route::post('/profil/email', [ProfilController::class, 'emailWechsel'])->middleware('throttle:5,10')->name('profil.email');
    Route::get('/profil/email/bestaetigen/{user}/{email}', [ProfilController::class, 'emailBestaetigen'])->middleware('signed')->name('profil.email.bestaetigen');
    Route::post('/push/test', [PushController::class, 'test'])->middleware('throttle:5,10')->name('push.test');
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
    Route::post('/kurse/antwort', [EinheitController::class, 'antwort'])->name('kurse.antwort');
    Route::post('/kurse/antwort/aufnahme', [UebungController::class, 'aufnahme'])->middleware('throttle:20,10')->name('uebung.aufnahme');
    Route::get('/kurse/antwort/{antwort}/aufnahme', [UebungController::class, 'hoeren'])->name('uebung.aufnahme.hoeren');
    Route::post('/kurse/uebung/{uebung}/praxis', [UebungController::class, 'praxis'])->name('uebung.praxis');
    Route::get('/kurse/{program:slug}', [KursController::class, 'show'])->name('kurse.show');
    Route::get('/kurse/{program:slug}/pdf', ArbeitsbuchPdfController::class)->name('kurse.pdf');
    Route::post('/kurse/{program:slug}/freigabe', [KursController::class, 'freigabe'])->name('kurse.freigabe');
    Route::get('/kurse/{program:slug}/schritt/{schritt}', [KursController::class, 'schritt'])->name('kurse.schritt');
    Route::get('/kurse/{program:slug}/einheit/{einheit}', [KursController::class, 'einheit'])->name('kurse.einheit');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/erledigt', [EinheitController::class, 'erledigt'])->name('kurse.erledigt');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/teilen', [EinheitController::class, 'teilen'])->name('kurse.teilen');
    Route::post('/kurse/{program:slug}/einheit/{einheit}/notiz', [EinheitController::class, 'notiz'])->name('kurse.notiz');

    // Termine
    Route::get('/termine', [TermineController::class, 'index'])->name('termine.index');
    Route::get('/suche', SucheController::class)->name('suche');
    Route::get('/mitteilungen', [MitteilungenController::class, 'index'])->name('mitteilungen');
    Route::get('/mitteilungen/{id}', [MitteilungenController::class, 'oeffnen'])->name('mitteilungen.oeffnen');
    Route::get('/buchen', [BuchenController::class, 'index'])->name('buchen.index');
    Route::get('/buchen/{art}', [BuchenController::class, 'zeiten'])->name('buchen.zeiten');
    Route::post('/buchen/{art}', [BuchenController::class, 'store'])->middleware('throttle:10,1')->name('buchen.store');
    Route::post('/buchungen/{booking}/absagen', [BuchenController::class, 'absagen'])->name('buchen.absagen');
    Route::get('/buchungen/{booking}/verschieben', [BuchenController::class, 'verschiebenZeiten'])->name('buchen.verschieben');
    Route::post('/buchungen/{booking}/verschieben', [BuchenController::class, 'verschieben'])->middleware('throttle:10,1')->name('buchen.verschieben.store');
    Route::get('/termine/{termin}', [TermineController::class, 'show'])->name('termine.show');
    Route::post('/termine/{termin}/dabei', [TermineController::class, 'dabei'])->name('termine.dabei');
    Route::post('/termine/{termin}/gesehen', [TermineController::class, 'gesehen'])->name('termine.gesehen');
    Route::post('/termine/{termin}/aufgabe', [TermineController::class, 'aufgabe'])->name('termine.aufgabe');
    Route::get('/termine/{termin}/kalender.ics', [KalenderController::class, 'termin'])->name('termine.ics');

    // Material und Merkliste
    Route::get('/material', [MaterialController::class, 'index'])->name('material.index');
    Route::get('/material/{material}', [MaterialController::class, 'show'])->name('material.show');
    Route::get('/material/{material}/datei', [MaterialController::class, 'datei'])->name('material.datei');
    Route::post('/material/{material}/gesehen', [MaterialController::class, 'gesehen'])->name('material.gesehen');
    Route::post('/merken', [MaterialController::class, 'merken'])->name('merken');
    Route::get('/merkliste', [MerklisteController::class, 'index'])->name('merkliste');

    // Impulse, Podcast, Themenfinder
    Route::get('/impulse', [ImpulseController::class, 'index'])->name('impulse.index');
    Route::get('/impulse/folge/{folge}', [ImpulseController::class, 'folge'])->name('impulse.folge');
    Route::get('/impulse/{post:slug}', [ImpulseController::class, 'show'])->name('impulse.show');
    Route::get('/themen', [ThemenController::class, 'index'])->name('themen.index');
    Route::get('/themen/{thema:slug}', [ThemenController::class, 'show'])->name('themen.show');

    // Nachschlagen (Fundus): ein Feld fuer alles, Vorschau, Teilen, Sammlungen
    Route::get('/nachschlagen', [NachschlagenController::class, 'index'])->name('nachschlagen.index');
    Route::get('/nachschlagen/vorschau/{art}/{id}', [NachschlagenController::class, 'vorschau'])->name('nachschlagen.vorschau')->where('art', '[a-z]+');
    Route::post('/nachschlagen/teilen', [NachschlagenController::class, 'teilen'])->name('nachschlagen.teilen');
    Route::delete('/nachschlagen/verlauf', [NachschlagenController::class, 'verlaufLeeren'])->name('nachschlagen.verlauf.leeren');
    Route::get('/sammlung/{sammlung}/{key}', [NachschlagenController::class, 'sammlung'])->name('nachschlagen.sammlung');

    // Arbeitsplatz (Team) in der App-Huelle: Ansicht umschalten, Coachees, Dossier, Assistent
    Route::post('/ansicht', AnsichtController::class)->name('ansicht');
    Route::get('/als', [AlsController::class, 'index'])->name('als');
    Route::post('/als/{user}', [AlsController::class, 'start'])->name('als.start');
    Route::delete('/als', [AlsController::class, 'ende'])->name('als.ende');
    Route::get('/assistent', [AssistentController::class, 'index'])->name('assistent');
    Route::post('/assistent/merken', [AssistentController::class, 'merken'])->name('assistent.merken');
    Route::post('/assistent/chat', [AssistentChatController::class, 'senden'])->middleware('throttle:30,10')->name('assistent.chat');
    Route::post('/assistent/chat/entscheiden', [AssistentChatController::class, 'entscheiden'])->name('assistent.chat.entscheiden');
    Route::post('/assistent/chat/neu', [AssistentChatController::class, 'neu'])->name('assistent.chat.neu');
    Route::delete('/assistent/wissen/{wissen}', [AssistentController::class, 'vergessen'])->name('assistent.vergessen');
    Route::get('/coach-vorschau/newsletter/{newsletter}', [NewsletterVorschauController::class, 'newsletter'])->name('newsletter.vorschau');
    Route::get('/coach-vorschau/serie/{serie}/{schritt?}', [NewsletterVorschauController::class, 'serie'])->name('newsletter.vorschau.serie');
    Route::get('/coach-vorschau/layout', [NewsletterVorschauController::class, 'layout'])->name('newsletter.vorschau.layout');
    Route::get('/coachees', [CoacheesController::class, 'index'])->name('coachees.index');
    Route::post('/coachees/frage', [CoacheesController::class, 'frage'])->name('coachees.frage');
    Route::post('/coachees/anlegen', [CoacheesController::class, 'anlegen'])->name('coachees.anlegen');
    Route::get('/coachees/{membership}', [DossierController::class, 'show'])->name('coachees.show');
    Route::post('/coachees/{membership}/gelesen', [DossierController::class, 'gelesen'])->name('coachees.gelesen');
    Route::post('/coachees/{membership}/nachricht', [DossierController::class, 'nachricht'])->name('coachees.nachricht');
    Route::post('/coachees/{membership}/notiz', [DossierController::class, 'notiz'])->name('coachees.notiz');
    Route::delete('/coachees/{membership}/notiz/{notiz}', [DossierController::class, 'notizLoeschen'])->name('coachees.notiz.loeschen');
    Route::post('/coachees/{membership}/aufgabe', [DossierController::class, 'aufgabe'])->name('coachees.aufgabe');
    Route::post('/coachees/{membership}/termin', [DossierController::class, 'termin'])->name('coachees.termin');
    Route::post('/coachees/{membership}/vorschlag', [DossierController::class, 'vorschlag'])->name('coachees.vorschlag');
    Route::post('/coachees/{membership}/zugang', [DossierController::class, 'zugang'])->name('coachees.zugang');
    Route::post('/coachees/{membership}/einladung', [DossierController::class, 'einladung'])->name('coachees.einladung');
    Route::post('/coachees/{membership}/vorbereitung', [DossierController::class, 'vorbereitung'])->name('coachees.vorbereitung');
    Route::post('/coachees/{membership}/kommentar', [DossierController::class, 'kommentar'])->name('coachees.kommentar');
    Route::get('/coachees/{membership}/rechnung/{rechnung}', [BuchhaltungController::class, 'pdfDossier'])->name('coachees.rechnung');

    // Werkzeuge fuer die Coach-Ausbildung
    Route::get('/werkzeuge', [WerkzeugeController::class, 'index'])->name('werkzeuge.index');
    Route::get('/werkzeuge/{tool:slug}', [WerkzeugeController::class, 'show'])->name('werkzeuge.show');

    // Mein Journal: Aufgaben, Notizen, Reflexion
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::get('/projekte', [ProjekteController::class, 'index'])->name('projekte.index');
    Route::post('/projekte', [ProjekteController::class, 'store'])->name('projekte.store');
    Route::post('/projekte/{projekt}', [ProjekteController::class, 'update'])->name('projekte.update');
    Route::post('/projekte/{projekt}/schritt', [ProjekteController::class, 'schritt'])->name('projekte.schritt');
    Route::delete('/projekte/{projekt}', [ProjekteController::class, 'destroy'])->name('projekte.destroy');
    Route::get('/aufgaben', [AufgabenController::class, 'index'])->name('aufgaben.index');
    Route::post('/aufgaben', [AufgabenController::class, 'store'])->name('aufgaben.store');
    Route::post('/aufgaben/{aufgabe}', [AufgabenController::class, 'update'])->name('aufgaben.update');
    Route::post('/aufgaben/{aufgabe}/haken', [AufgabenController::class, 'haken'])->name('aufgaben.haken');
    Route::post('/aufgaben/{aufgabe}/tag', [AufgabenController::class, 'tag'])->name('aufgaben.tag');
    Route::delete('/aufgaben/{aufgabe}', [AufgabenController::class, 'destroy'])->name('aufgaben.destroy');
    Route::get('/anhaenge/suche', [AnhaengeController::class, 'suche'])->name('anhaenge.suche');
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

    // Fragen an die Coachin im Kursraum, Community = alle Fragen aus meinen Kursen
    Route::get('/community', [FragenController::class, 'community'])->name('community');
    Route::get('/community/wer-ist-dabei', [FragenController::class, 'leute'])->name('community.leute');
    Route::post('/community/fragen', [FragenController::class, 'communityStore'])->middleware('throttle:20,10')->name('community.fragen.store');
    Route::get('/hilfe', [ProfilController::class, 'hilfeSeite'])->name('hilfe');
    Route::get('/kurse/{program:slug}/fragen', [FragenController::class, 'index'])->name('kurse.fragen');
    Route::post('/kurse/{program:slug}/fragen', [FragenController::class, 'store'])->middleware('throttle:20,10')->name('kurse.fragen.store');
    Route::get('/fragen/{frage}', [FragenController::class, 'show'])->name('fragen.show');
    Route::post('/fragen/{frage}/antworten', [FragenController::class, 'antworten'])->middleware('throttle:30,10')->name('fragen.antworten');
    Route::get('/fragen/{frage}/neu', [FragenController::class, 'neu'])->name('fragen.neu');
    Route::post('/fragen/{frage}/folgen', [FragenController::class, 'folgen'])->name('fragen.folgen');
    Route::post('/fragen/{frage}/status', [FragenController::class, 'status'])->name('fragen.status');
    Route::post('/fragen/{frage}/call', [FragenController::class, 'call'])->name('fragen.call');
    Route::delete('/fragen/{frage}', [FragenController::class, 'destroy'])->name('fragen.destroy');
    Route::delete('/antworten/{antwort}', [FragenController::class, 'antwortLoeschen'])->name('fragen.antwort.loeschen');
    Route::patch('/antworten/{antwort}', [FragenController::class, 'antwortAendern'])->name('fragen.antwort.aendern');
    Route::post('/antworten/{antwort}/beste', [FragenController::class, 'beste'])->name('fragen.antwort.beste');
    Route::post('/element/{typ}/{id}/schnell', [ElementController::class, 'schnell'])->where('typ', 'note|task|reflection')->name('element.schnell');
    Route::get('/notizen/{notiz}/foto', [NotizenController::class, 'foto'])->name('notizen.foto');
    Route::post('/reaktion/{typ}/{id}', [ReaktionController::class, 'toggle'])->where('typ', 'note|task|reflection|projekt|question|comment')->middleware('throttle:60,1')->name('reaktion');
    Route::post('/kommentar', [KommentarController::class, 'store'])->middleware('throttle:30,1')->name('kommentar.store');
    Route::delete('/kommentar/{kommentar}', [KommentarController::class, 'destroy'])->name('kommentar.destroy');
});
