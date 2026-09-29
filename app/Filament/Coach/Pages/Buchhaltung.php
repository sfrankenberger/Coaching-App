<?php

namespace App\Filament\Coach\Pages;

use App\Enums\Role;
use App\Shop\Bexio;
use App\Shop\Buchhaltung as Anbindung;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Buchhaltung des Mandanten: welches System (bexio, spaeter weitere) und der Zugang dazu.
 * Nur die Inhaberin. Geheimnisse werden gespeichert, aber nie wieder angezeigt.
 */
class Buchhaltung extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Buchhaltung';

    protected static ?string $title = 'Buchhaltung';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.coach.buchhaltung';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_platform_admin || $user?->roleIn() === Role::Owner);
    }

    public function mount(): void
    {
        $tenant = app(CurrentTenant::class)->getOrFail();
        $b = (array) $tenant->setting('buchhaltung', []);
        $this->form->fill([
            'anbieter' => $b['anbieter'] ?? null,
            'client_id' => $b['bexio']['client_id'] ?? null,
            'client_secret' => null,
            'token' => null,
        ]);
    }

    /** Stand der Verbindung fuer die Anzeige. */
    public function stand(): array
    {
        $tenant = app(CurrentTenant::class)->getOrFail();
        $b = Anbindung::fuer($tenant);
        $bexio = $b instanceof Bexio ? $b : new Bexio($tenant);
        $e = $bexio->einstellungen();

        return $bexio->stand() + [
            'verbunden' => $b?->verbunden() ?? false,
            'client_secret' => filled($e['client_secret'] ?? null),
            'token' => filled($e['token'] ?? null),
            'rueckkehr' => route('buchhaltung.bexio.rueckkehr'),
            'start' => route('buchhaltung.bexio.start'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('System')->description('Jede Coachin hat ihre eigene Buchhaltung. Die App liest daraus die Rechnungen der Personen (Profil und Dossier) und holt die PDFs.')->schema([
                Select::make('anbieter')->label('Buchhaltung')->options(Anbindung::ANBIETER)->placeholder('Keine')->native(false)->live(),
            ]),
            Section::make('Zugang zu bexio')->description('Entweder eine dauerhafte Verbindung (Client-ID und Client-Secret aus dem bexio-Entwicklerportal, danach "Mit bexio verbinden") oder ein fester Zugriffstoken. Die Verbindung gehört der App allein, nie das Refresh-Token eines anderen Systems hier eintragen.')->schema([
                TextInput::make('client_id')->label('Client-ID')->maxLength(200),
                TextInput::make('client_secret')->label('Client-Secret')->password()->revealable()->maxLength(500)->placeholder(fn () => $this->stand()['client_secret'] ? 'hinterlegt, leer lassen zum Behalten' : ''),
                TextInput::make('token')->label('Fester Zugriffstoken (statt OAuth)')->password()->revealable()->maxLength(4000)->placeholder(fn () => $this->stand()['token'] ? 'hinterlegt, leer lassen zum Behalten' : ''),
            ])->columns(2)->visible(fn (Get $get) => $get('anbieter') === 'bexio'),
        ])->statePath('data');
    }

    public function speichern(): void
    {
        $data = $this->form->getState();
        $tenant = app(CurrentTenant::class)->getOrFail();
        $s = $tenant->settings ?? [];
        $s['buchhaltung']['anbieter'] = filled($data['anbieter'] ?? null) ? $data['anbieter'] : null;
        $bexio = $s['buchhaltung']['bexio'] ?? [];
        $bexio['client_id'] = filled($data['client_id'] ?? null) ? trim($data['client_id']) : null;
        if (filled($data['client_secret'] ?? null)) {
            $bexio['client_secret'] = trim($data['client_secret']);
        }
        if (filled($data['token'] ?? null)) {
            $bexio['token'] = trim($data['token']);
            $bexio['verbunden_am'] = now()->toIso8601String();
        }
        $s['buchhaltung']['bexio'] = $bexio;
        $tenant->forceFill(['settings' => $s])->save();
        $this->form->fill(['anbieter' => $s['buchhaltung']['anbieter'], 'client_id' => $bexio['client_id'], 'client_secret' => null, 'token' => null]);

        Notification::make()->title('Gespeichert')->success()->send();
    }

    public function trennen(): void
    {
        (new Bexio(app(CurrentTenant::class)->getOrFail()))->trennen();
        Notification::make()->title('Verbindung getrennt')->success()->send();
    }
}
