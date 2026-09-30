<?php

namespace App\Filament\Coach\Pages;

use App\Audio\Transkript;
use App\Enums\Role;
use App\Tenancy\CurrentTenant;
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
}
