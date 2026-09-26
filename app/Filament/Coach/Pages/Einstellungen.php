<?php

namespace App\Filament\Coach\Pages;

use App\Enums\Role;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Einstellungen des Mandanten, die die Coachin selbst pflegt: Aussehen, Absender,
 * Website, Feeds, Telegram-Bot. Geheimnisse (Tokens, Schluessel) bleiben in der Plattform.
 */
class Einstellungen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'Einstellungen';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.coach.einstellungen';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_platform_admin || $user?->roleIn() === Role::Owner);
    }

    public function mount(): void
    {
        $tenant = app(CurrentTenant::class)->getOrFail();
        $b = $tenant->branding ?? [];
        $s = $tenant->settings ?? [];
        $this->form->fill([
            'app_name' => $b['app_name'] ?? null,
            'short_name' => $b['short_name'] ?? null,
            'primary' => $b['primary'] ?? null,
            'primary_contrast' => $b['primary_contrast'] ?? null,
            'logo_url' => $b['logo_url'] ?? null,
            'icon_url' => $b['icon_url'] ?? null,
            'coach_name' => $s['coach_name'] ?? null,
            'website' => $s['website'] ?? null,
            'shop_url' => $s['shop']['url'] ?? null,
            'ausbildung_url' => $s['ausbildung_url'] ?? null,
            'ai_wissen' => $s['ai']['wissen'] ?? null,
            'ai_fundus_hinweis' => $s['ai']['fundus_hinweis'] ?? null,
            'from_name' => $s['mail']['from_name'] ?? null,
            'from_address' => $s['mail']['from_address'] ?? null,
            'reply_to' => $s['mail']['reply_to'] ?? null,
            'feeds' => array_values((array) ($s['feeds'] ?? [])),
            'telegram_bot_username' => $s['telegram']['bot_username'] ?? null,
            'test_only' => (bool) ($s['notifications']['test_only'] ?? false),
            'test_emails' => array_values((array) ($s['notifications']['test_emails'] ?? [])),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Testbetrieb')->description('Solange die App parallel zum bisherigen Mitgliederbereich läuft: Benachrichtigungen (Erinnerungen, Abendmail, Rundnachrichten, Push) gehen nur an diese Adressen. Anmeldelinks funktionieren für alle.')->schema([
                Toggle::make('test_only')->label('Testbetrieb an')->live(),
                TagsInput::make('test_emails')->label('Diese Adressen bekommen Benachrichtigungen')->placeholder('Adresse eingeben, Enter')
                    ->visible(fn ($get) => (bool) $get('test_only'))->nestedRecursiveRules(['email']),
            ]),
            Section::make('Aussehen')->description('Name und Farben der App. Weitere Feinheiten stellt die Plattform ein.')->schema([
                TextInput::make('app_name')->label('Name der App')->maxLength(60),
                TextInput::make('short_name')->label('Kurzname (Startbildschirm)')->maxLength(12),
                ColorPicker::make('primary')->label('Hauptfarbe'),
                ColorPicker::make('primary_contrast')->label('Schrift auf der Hauptfarbe'),
                TextInput::make('logo_url')->label('Logo (URL)')->url()->maxLength(500),
                TextInput::make('icon_url')->label('App-Icon (URL, 512 x 512)')->url()->maxLength(500),
            ])->columns(2),
            Section::make('Du und deine Website')->schema([
                TextInput::make('coach_name')->label('Dein Vorname (für die Anrede in Texten)')->maxLength(60),
                TextInput::make('website')->label('Website')->url()->maxLength(200),
                TextInput::make('shop_url')->label('Wo man deine Angebote kauft')->url()->maxLength(300)->helperText('Dorthin führt im Nachschlagen die Tür bei gesperrten Kursen, wenn der Kurs keine eigene Verkaufsseite hat.'),
                TextInput::make('ausbildung_url')->label('Seite der Coach-Ausbildung')->url()->maxLength(300)->helperText('Dorthin führt die Tür bei den Werkzeugen.'),
            ])->columns(2),
            Section::make('KI')->description('Der Schlüssel liegt in der Plattform. Hier, was die KI von dir wissen soll.')->schema([
                Textarea::make('ai_wissen')->label('Was dein Assistent zusätzlich wissen soll')->rows(6)->maxLength(8000)
                    ->helperText('Eigene Regeln, Abläufe, Namen. Der Assistent kennt die App schon, das hier kommt dazu.'),
                Textarea::make('ai_fundus_hinweis')->label('Hinweis für die Suche im Nachschlagen')->rows(2)->maxLength(500)
                    ->helperText('Ein Satz über deinen Ansatz, zum Beispiel mit welcher Methode du arbeitest. Hilft der KI beim Auswählen.'),
            ]),
            Section::make('Absender der Mails')->schema([
                TextInput::make('from_name')->label('Absendername')->maxLength(80),
                TextInput::make('from_address')->label('Absenderadresse')->email()->maxLength(190)->helperText('Muss zur Mail-Domain passen, die der Server verschicken darf.'),
                TextInput::make('reply_to')->label('Antworten an')->email()->maxLength(190),
            ])->columns(3),
            Section::make('Impulse und Podcast per Feed')->description('Wird stündlich geholt.')->schema([
                Repeater::make('feeds')->label('')->schema([
                    Select::make('type')->label('Art')->options(['post' => 'Beiträge', 'podcast' => 'Podcast'])->required()->native(false),
                    TextInput::make('url')->label('Feed-Adresse')->url()->required()->maxLength(500),
                    TextInput::make('show')->label('Name der Sendung')->maxLength(80),
                    TextInput::make('limit')->label('Höchstens')->numeric()->default(20),
                ])->columns(4)->defaultItems(0)->addActionLabel('Feed hinzufügen'),
            ]),
            Section::make('Telegram')->description('Der Bot-Token liegt in der Plattform. Hier nur der Name, den die Personen sehen.')->schema([
                TextInput::make('telegram_bot_username')->label('Bot-Name (ohne @)')->maxLength(60),
            ]),
        ])->statePath('data');
    }

    public function speichern(): void
    {
        $data = $this->form->getState();
        $tenant = app(CurrentTenant::class)->getOrFail();
        $b = $tenant->branding ?? [];
        $s = $tenant->settings ?? [];
        foreach (['app_name', 'short_name', 'primary', 'primary_contrast', 'logo_url', 'icon_url'] as $k) {
            $b[$k] = filled($data[$k] ?? null) ? $data[$k] : null;
        }
        $s['coach_name'] = filled($data['coach_name']) ? $data['coach_name'] : null;
        $s['website'] = filled($data['website']) ? $data['website'] : null;
        $s['shop'] = array_merge($s['shop'] ?? [], ['url' => filled($data['shop_url'] ?? null) ? $data['shop_url'] : null]);
        $s['ausbildung_url'] = filled($data['ausbildung_url'] ?? null) ? $data['ausbildung_url'] : null;
        $s['ai'] = array_merge($s['ai'] ?? [], ['wissen' => filled($data['ai_wissen'] ?? null) ? $data['ai_wissen'] : null, 'fundus_hinweis' => filled($data['ai_fundus_hinweis'] ?? null) ? $data['ai_fundus_hinweis'] : null]);
        $s['mail'] = array_merge($s['mail'] ?? [], ['from_name' => $data['from_name'] ?: null, 'from_address' => $data['from_address'] ?: null, 'reply_to' => $data['reply_to'] ?: null]);
        $s['feeds'] = array_values(array_map(fn ($f) => array_filter(['type' => $f['type'] ?? 'post', 'url' => $f['url'] ?? null, 'show' => $f['show'] ?? null, 'limit' => (int) ($f['limit'] ?? 0) ?: null]), $data['feeds'] ?? []));
        $s['telegram'] = array_merge($s['telegram'] ?? [], ['bot_username' => $data['telegram_bot_username'] ?: null]);
        $s['notifications'] = array_merge($s['notifications'] ?? [], [
            'test_only' => (bool) ($data['test_only'] ?? false),
            'test_emails' => array_values(array_unique(array_map(fn ($e) => strtolower(trim($e)), (array) ($data['test_emails'] ?? $s['notifications']['test_emails'] ?? [])))),
        ]);
        $tenant->forceFill(['branding' => $b, 'settings' => $s])->save();

        Notification::make()->title('Gespeichert')->success()->send();
    }
}
