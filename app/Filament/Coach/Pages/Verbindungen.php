<?php

namespace App\Filament\Coach\Pages;

use App\Audio\Transkript;
use App\Enums\Role;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use App\Tenancy\MandantenMail;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Schluessel und Verbindungen je Mandant: KI, Abschrift von Sprachnachrichten, Vimeo, Zoom, Telegram, Google.
 * Geheimnisse werden nie angezeigt, nur "hinterlegt". Leer lassen behaelt den Wert, ein Minus entfernt ihn.
 * bexio hat seine eigene Seite (Buchhaltung).
 */
class Verbindungen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Schlüssel und Verbindungen';

    protected static ?string $title = 'Schlüssel und Verbindungen';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.coach.einstellungen';

    public ?array $data = [];

    /** Feld => Pfad in settings, alles Geheimnisse ausser den mit "offen" markierten. */
    protected const FELDER = [
        'anthropic_key' => ['ai.anthropic_key', true],
        'ai_model' => ['ai.model', false],
        'audio_anbieter' => ['audio.anbieter', false],
        'assemblyai_key' => ['audio.assemblyai_key', true],
        'openai_key' => ['audio.openai_key', true],
        'audio_sprache' => ['audio.sprache', false],
        'vimeo_token' => ['vimeo.token', true],
        'zoom_account_id' => ['zoom.account_id', false],
        'zoom_client_id' => ['zoom.client_id', false],
        'zoom_client_secret' => ['zoom.client_secret', true],
        'telegram_bot_token' => ['telegram.bot_token', true],
        'google_service_account' => ['google.service_account', true],
        'stripe_public_key' => ['stripe.public_key', false],
        'stripe_secret_key' => ['stripe.secret_key', true],
        'stripe_webhook_secret' => ['stripe.webhook_secret', true],
        'mailgun_domain' => ['mail.mailgun_domain', false],
        'mailgun_secret' => ['mail.mailgun_secret', true],
        'mailgun_endpoint' => ['mail.mailgun_endpoint', false],
        'google_client_id' => ['oauth.google.client_id', false],
        'google_client_secret' => ['oauth.google.client_secret', true],
        'apple_client_id' => ['oauth.apple.client_id', false],
        'apple_team_id' => ['oauth.apple.team_id', false],
        'apple_key_id' => ['oauth.apple.key_id', false],
        'apple_private_key' => ['oauth.apple.private_key', true],
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_platform_admin || $user?->roleIn() === Role::Owner);
    }

    public function mount(): void
    {
        $tenant = app(CurrentTenant::class)->getOrFail();
        $fill = [];
        foreach (self::FELDER as $feld => [$pfad, $geheim]) {
            $wert = $tenant->setting($pfad);
            $fill[$feld] = $geheim ? null : (is_array($wert) ? json_encode($wert) : $wert);
        }
        $this->form->fill($fill);
    }

    /** "hinterlegt, endet auf ..." fuer Geheimnisse, ohne den Wert zu zeigen. */
    protected function stand(string $pfad): string
    {
        $wert = app(CurrentTenant::class)->get()?->setting($pfad);
        if (is_array($wert)) {
            $wert = json_encode($wert);
        }
        $wert = (string) $wert;

        return $wert === '' ? 'nicht hinterlegt' : 'hinterlegt, endet auf '.substr($wert, -4).', leer lassen zum Behalten, Minus zum Entfernen';
    }

    protected function geheim(string $name, string $label, string $pfad): TextInput
    {
        return TextInput::make($name)->label($label)->password()->revealable()->maxLength(4000)->placeholder(fn () => $this->stand($pfad));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('KI (Anthropic)')->description('Für Assistent, Zusammenfassungen, Themenfinder und die Suche. Ohne eigenen Schlüssel gilt der der Plattform.')->schema([
                $this->geheim('anthropic_key', 'API-Schlüssel', 'ai.anthropic_key'),
                TextInput::make('ai_model')->label('Modell (leer: Vorgabe der Plattform)')->maxLength(80)->placeholder(config('ai.model')),
            ])->columns(2),
            Section::make('Sprachnachrichten abschreiben')->description('Sprachnachrichten im Chat bekommen ein Transkript. Braucht einen Audio-Dienst: AssemblyAI (wie im alten Bereich) oder OpenAI Whisper.')->schema([
                Select::make('audio_anbieter')->label('Dienst')->options(Transkript::ANBIETER)->placeholder('Kein Transkript')->native(false),
                TextInput::make('audio_sprache')->label('Sprache')->maxLength(5)->placeholder('de'),
                $this->geheim('assemblyai_key', 'AssemblyAI-Schlüssel', 'audio.assemblyai_key'),
                $this->geheim('openai_key', 'OpenAI-Schlüssel', 'audio.openai_key'),
            ])->columns(2),
            Section::make('Vimeo')->description('Aufzeichnungen zuordnen und Abschriften holen.')->schema([
                $this->geheim('vimeo_token', 'Zugriffstoken', 'vimeo.token'),
            ]),
            Section::make('Zoom')->description('Wer war im Call (Server-to-Server OAuth App).')->schema([
                TextInput::make('zoom_account_id')->label('Account-ID')->maxLength(120),
                TextInput::make('zoom_client_id')->label('Client-ID')->maxLength(120),
                $this->geheim('zoom_client_secret', 'Client-Secret', 'zoom.client_secret'),
            ])->columns(3),
            Section::make('Telegram')->description('Der Bot, über den Erinnerungen laufen. Den Namen stellst du in den Einstellungen ein.')->schema([
                $this->geheim('telegram_bot_token', 'Bot-Token', 'telegram.bot_token'),
            ]),
            Section::make('Google-Kalender')->description('Dienstkonto für die Buchung (JSON aus der Google Cloud Console).')->schema([
                Textarea::make('google_service_account')->label('Dienstkonto (JSON)')->rows(4)->placeholder(fn () => $this->stand('google.service_account')),
            ]),
            Section::make('Mailgun')->description('Mails über das eigene Mailgun-Konto. Domain und API-Schlüssel aus dem Mailgun-Dashboard (Sending, Domain settings). Leer: die Mails gehen über die Plattform. Absenderadresse und -name stehen in den Einstellungen.')->schema([
                TextInput::make('mailgun_domain')->label('Sende-Domain')->placeholder('mg.deine-domain.ch')->maxLength(200),
                $this->geheim('mailgun_secret', 'API-Schlüssel', 'mail.mailgun_secret'),
                Select::make('mailgun_endpoint')->label('Region')->options(['api.eu.mailgun.net' => 'EU (api.eu.mailgun.net)', 'api.mailgun.net' => 'USA (api.mailgun.net)'])->default('api.eu.mailgun.net')->native(false),
            ])->columns(3),
            Section::make('Anmelden mit Google')->description(fn () => 'OAuth-Client in der Google Cloud Console (Typ Webanwendung). Weiterleitungs-URI dort eintragen: '.route('anmelden.dienst.zurueck', ['dienst' => 'google']).'. Sobald beides hinterlegt ist, erscheint der Knopf auf der Anmeldeseite.')->schema([
                TextInput::make('google_client_id')->label('Client-ID')->maxLength(200),
                $this->geheim('google_client_secret', 'Client-Secret', 'oauth.google.client_secret'),
            ])->columns(2),
            Section::make('Anmelden mit Apple')->description(fn () => 'Aus dem Apple Developer Account: Services-ID (als Client-ID), Team-ID, Key-ID und der Inhalt der .p8-Datei. Das Client-Secret erzeugt die App daraus selbst und erneuert es, es muss nie von Hand gepflegt werden. Return-URL bei Apple: '.route('anmelden.dienst.zurueck', ['dienst' => 'apple']).'.')->schema([
                TextInput::make('apple_client_id')->label('Services-ID (Client-ID)')->maxLength(200)->placeholder('ch.deine-domain.app'),
                TextInput::make('apple_team_id')->label('Team-ID')->maxLength(20),
                TextInput::make('apple_key_id')->label('Key-ID')->maxLength(20),
                Textarea::make('apple_private_key')->label('Privater Schlüssel (.p8)')->rows(4)->placeholder(fn () => $this->stand('oauth.apple.private_key'))->columnSpanFull(),
            ])->columns(3),
            Section::make('Stripe')->description('Kasse mit Karte und Twint (Etappe 10). Schlüssel aus dem Stripe-Dashboard unter Entwickler, API-Schlüssel; das Webhook-Secret kommt beim Anlegen des Webhooks. Die Kasse nutzt sie, sobald sie freigeschaltet ist.')->schema([
                TextInput::make('stripe_public_key')->label('Publishable Key')->placeholder('pk_live_...')->maxLength(200),
                $this->geheim('stripe_secret_key', 'Secret Key', 'stripe.secret_key'),
                $this->geheim('stripe_webhook_secret', 'Webhook-Secret', 'stripe.webhook_secret'),
            ])->columns(3),
        ])->statePath('data');
    }

    public function speichern(): void
    {
        $data = $this->form->getState();
        $tenant = app(CurrentTenant::class)->getOrFail();
        $s = $tenant->settings ?? [];
        foreach (self::FELDER as $feld => [$pfad, $geheim]) {
            $wert = $data[$feld] ?? null;
            $wert = is_string($wert) ? trim($wert) : $wert;
            if ($geheim) {
                if ($wert === null || $wert === '') {
                    continue;   // leer lassen behaelt den Wert
                }
                $wert = $wert === '-' ? null : $wert;
                if ($feld === 'google_service_account' && $wert !== null) {
                    $json = json_decode($wert, true);
                    if (! is_array($json)) {
                        Notification::make()->title('Google-Dienstkonto ist kein gültiges JSON')->danger()->send();

                        return;
                    }
                    $wert = $json;
                }
            } else {
                $wert = $wert === '' ? null : $wert;
            }
            data_set($s, $pfad, $wert);
        }
        $tenant->forceFill(['settings' => $s])->save();

        Notification::make()->title('Gespeichert')->success()->send();
    }

    /** Eine Test-Mail an die eigene Adresse, ueber den Weg, der gerade gilt (eigenes Mailgun oder Plattform). */
    public function testMail(): void
    {
        $tenant = app(CurrentTenant::class)->getOrFail()->fresh();
        app(CurrentTenant::class)->set($tenant);
        $user = auth()->user();
        try {
            app(Notifier::class)->send([$user->id], new Nachricht(
                titel: 'Test-Mail aus '.app(Branding::class)->appName(),
                text: 'Wenn diese Mail ankommt, stimmt der Mailversand'.(MandantenMail::eigenes($tenant) ? ' über dein Mailgun-Konto ('.$tenant->setting('mail.mailgun_domain').').' : ' über die Plattform.')."\nAbsender: ".($tenant->setting('mail.from_address') ?: config('mail.from.address')),
                url: url('/coach/verbindungen'), anlass: 'system', tag: 'testmail', mailImmer: true, inApp: false, knopf: 'Zu den Verbindungen',
            ));
            Notification::make()->title('Test-Mail an '.$user->email.' geschickt')->body('Schau ins Postfach, auch im Spam.')->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Test-Mail fehlgeschlagen')->body(mb_substr($e->getMessage(), 0, 300))->danger()->send();
        }
    }
}
