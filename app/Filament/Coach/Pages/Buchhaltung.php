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
        $v = $b['bexio']['schreiben'] ?? [];
        $this->form->fill([
            'anbieter' => $b['anbieter'] ?? null,
            'client_id' => $b['bexio']['client_id'] ?? null,
            'client_secret' => null,
            'token' => null,
            'zugang_bei_rechnung' => $b['zugang_bei_rechnung'] ?? 'sofort',
            'account_id' => $v['account_id'] ?? null,
            'tax_id' => $v['tax_id'] ?? null,
            'bank_account_id' => $v['bank_account_id'] ?? null,
            'zahlung_bank_account_id' => $v['zahlung_bank_account_id'] ?? null,
            'frist' => $v['frist'] ?? 30,
            'kopie_mail' => $v['kopie_mail'] ?? null,
            'absender' => $v['absender'] ?? null,
            'template_slug' => $v['template_slug'] ?? null,
        ]);
    }

    /** Stammdaten aus bexio: Konten, Steuern, Bankkonten fuer die Auswahl. */
    protected function stamm(string $k): array
    {
        $s = (array) app(CurrentTenant::class)->getOrFail()->setting('buchhaltung.bexio.stammdaten.'.$k, []);

        return array_map('strval', $s);
    }

    public function stammdatenLaden(): void
    {
        try {
            $s = (new Bexio(app(CurrentTenant::class)->getOrFail()))->stammdaten();
            Notification::make()->title('Stammdaten geladen')->body(count($s['konten']).' Ertragskonten, '.count($s['bank']).' Bankkonten, '.count($s['steuern']).' Steuersätze')->success()->send();
            $this->mount();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Das hat nicht geklappt')->body($e->getMessage())->danger()->send();
        }
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
            Section::make('Rechnungen schreiben')->description('Beim Verkaufen im Dossier legt die App Kontakt, Rechnung und bei "bereits bezahlt" den Zahlungseingang in bexio an. Die Auswahl kommt aus den Stammdaten, nach dem Verbinden einmal laden.')->schema([
                Select::make('account_id')->label('Ertragskonto')->options(fn () => $this->stamm('konten'))->searchable()->native(false),
                Select::make('tax_id')->label('Steuersatz')->options(fn () => $this->stamm('steuern'))->placeholder('Keine MWST')->native(false),
                Select::make('bank_account_id')->label('Bankkonto auf der Rechnung')->options(fn () => $this->stamm('bank'))->native(false),
                Select::make('zahlung_bank_account_id')->label('Bankkonto für Zahlungseingänge')->options(fn () => $this->stamm('bank'))->native(false),
                TextInput::make('frist')->label('Zahlungsfrist in Tagen')->numeric()->minValue(0)->maxValue(120),
                TextInput::make('kopie_mail')->label('Kopie jeder Rechnung an')->email()->helperText('bexio erzeugt den Link zum Online-Bezahlen erst beim Versand, darum geht eine Kopie an diese Adresse.'),
                TextInput::make('absender')->label('Unterschrift in der Rechnung')->placeholder('wie der Name der Coachin'),
                TextInput::make('template_slug')->label('Vorlage (Slug)')->placeholder('Standard'),
                Select::make('zugang_bei_rechnung')->label('Zugang bei Kauf auf Rechnung')->options(['sofort' => 'sofort, Rechnung läuft nebenher', 'bezahlt' => 'erst nach Zahlungseingang'])->native(false)->columnSpanFull(),
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
        $v = $bexio['schreiben'] ?? [];
        foreach (['account_id', 'tax_id', 'bank_account_id', 'zahlung_bank_account_id'] as $k) {
            $v[$k] = filled($data[$k] ?? null) ? (int) $data[$k] : null;
        }
        $v['frist'] = (int) ($data['frist'] ?? 30);
        foreach (['kopie_mail', 'absender', 'template_slug'] as $k) {
            $v[$k] = filled($data[$k] ?? null) ? trim($data[$k]) : null;
        }
        $bexio['schreiben'] = $v;
        $s['buchhaltung']['bexio'] = $bexio;
        $s['buchhaltung']['zugang_bei_rechnung'] = ($data['zugang_bei_rechnung'] ?? 'sofort') === 'bezahlt' ? 'bezahlt' : 'sofort';
        $tenant->forceFill(['settings' => $s])->save();
        $this->mount();

        Notification::make()->title('Gespeichert')->success()->send();
    }

    public function trennen(): void
    {
        (new Bexio(app(CurrentTenant::class)->getOrFail()))->trennen();
        Notification::make()->title('Verbindung getrennt')->success()->send();
    }
}
