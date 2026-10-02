<?php

namespace App\Filament\Coach\Pages;

use App\Models\Membership;
use App\Models\Program;
use App\Models\Rundnachricht as Eintrag;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Notifications\Rundsendung;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Eine Nachricht an alle oder an ein Programm: Push, Mail, auf Wunsch ins Gruppengespraech.
 * Entwuerfe lassen sich speichern und spaeter laden, eine Testmail geht an die eigene Adresse,
 * verschickte Rundnachrichten stehen unten im Protokoll.
 */
class Rundnachricht extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Rundnachricht';

    protected static ?string $title = 'Rundnachricht';

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.coach.rundnachricht';

    public ?array $data = [];

    /** Geladener Entwurf, wird beim Speichern aktualisiert und beim Senden zum Protokolleintrag. */
    public ?int $entwurfId = null;

    public const LEER = ['an' => 'alle', 'program_id' => null, 'user_ids' => [], 'titel' => null, 'text' => null, 'url' => null, 'kanaele' => ['push', 'mail'], 'chat' => false, 'persoenlich' => false, 'mail_alle' => false];

    public function mount(): void
    {
        $this->entwurfAusEinstellungen();
        $neuester = Eintrag::where('status', 'entwurf')->latest('updated_at')->first();
        $neuester ? $this->laden($neuester->id) : $this->form->fill(self::LEER);
    }

    /** Uebergang: ein Entwurf aus tenants.settings.rundnachricht.entwurf wird einmalig zum Eintrag. */
    protected function entwurfAusEinstellungen(): void
    {
        $tenant = app(CurrentTenant::class)->get();
        $e = $tenant?->setting('rundnachricht.entwurf');
        if (! is_array($e)) {
            return;
        }
        Eintrag::create(Eintrag::ausFormular($e) + ['user_id' => auth()->id(), 'status' => 'entwurf']);
        $s = $tenant->settings;
        unset($s['rundnachricht']['entwurf']);
        $tenant->forceFill(['settings' => $s])->save();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('An wen')->schema([
                Select::make('an')->label('Empfänger')->options(['alle' => 'Alle aktiven Personen', 'programm' => 'Ein Programm', 'einzelne' => 'Einzelne Personen'])->required()->native(false)->live(),
                Select::make('user_ids')->label('Personen')->multiple()->searchable()->native(false)
                    ->options(fn () => Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->with('user:id,name')->get()->filter->user->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name])->sort()->all())
                    ->visible(fn ($get) => $get('an') === 'einzelne')->required(fn ($get) => $get('an') === 'einzelne'),
                Select::make('program_id')->label('Programm')->options(fn () => Program::orderBy('title')->pluck('title', 'id')->all())->native(false)
                    ->visible(fn ($get) => $get('an') === 'programm')->required(fn ($get) => $get('an') === 'programm'),
                Toggle::make('chat')->label('Auch als Nachricht ins Gruppengespräch')->visible(fn ($get) => $get('an') === 'programm' && ! $get('persoenlich')),
                Toggle::make('persoenlich')->label('Als persönliche Nachricht ins 1:1-Gespräch')->live()
                    ->helperText('Jede Person bekommt die Nachricht in ihr eigenes Gespräch und kann direkt antworten. {vorname} wird ersetzt.'),
            ])->columns(2),
            Section::make('Was')->schema([
                TextInput::make('titel')->label('Titel')->required(fn ($get) => ! $get('persoenlich'))->visible(fn ($get) => ! $get('persoenlich'))->maxLength(120),
                Textarea::make('text')->label('Text')->required()->rows(8)->maxLength(2000)->helperText('Kurz und warm. Der Text erscheint in Push und Mail.'),
                TextInput::make('url')->label('Link (optional)')->url()->maxLength(500)->helperText('Sonst führt der Knopf auf die Startseite.'),
                CheckboxList::make('kanaele')->label('Kanäle')->options(['push' => 'Push und Telegram (wer es hat)', 'mail' => 'Mail (wer kein Push hat)'])
                    ->required(fn ($get) => ! $get('persoenlich'))->visible(fn ($get) => ! $get('persoenlich')),
                Toggle::make('mail_alle')->label('Mail an alle, auch an wer Push hat')->visible(fn ($get) => ! $get('persoenlich'))
                    ->helperText('Für Ankündigungen, die in den Posteingang gehören. Sonst bekommt eine Person mit Push nur den Push.'),
            ]),
        ])->statePath('data');
    }

    /** Entwurf speichern, ohne zu pruefen, ob alles ausgefuellt ist. */
    public function entwurfAction(): Action
    {
        return Action::make('entwurf')->label('Als Entwurf speichern')->icon('heroicon-o-document')->color('gray')
            ->action(function () {
                $data = $this->form->getRawState();
                if (blank($data['text'] ?? null) && blank($data['titel'] ?? null)) {
                    Notification::make()->title('Nichts zu speichern')->body('Schreib zuerst einen Titel oder einen Text.')->warning()->send();

                    return;
                }
                $e = ($this->entwurfId ? Eintrag::where('status', 'entwurf')->find($this->entwurfId) : null) ?? new Eintrag(['user_id' => auth()->id(), 'status' => 'entwurf']);
                $e->fill(Eintrag::ausFormular($data))->save();
                $this->entwurfId = $e->id;
                Notification::make()->title('Entwurf gespeichert')->body('Er wird beim nächsten Öffnen wieder geladen.')->success()->send();
            });
    }

    /** Testmail an die eigene Adresse, genau so, wie sie bei den Empfaengerinnen ankommt. */
    public function testAction(): Action
    {
        return Action::make('test')->label('Testmail an mich')->icon('heroicon-o-envelope')->color('gray')
            ->action(function () {
                $data = $this->form->getState();
                $user = auth()->user();
                $text = str_replace(['{vorname}', '{name}'], [$user->vorname(), $user->name], trim($data['text']));
                app(Notifier::class)->send([$user->id], new Nachricht(
                    titel: '[Test] '.(trim((string) ($data['titel'] ?? '')) ?: mb_substr($text, 0, 60)),
                    text: $text,
                    url: filled($data['url'] ?? null) ? $data['url'] : route('home'),
                    anlass: 'system',
                    tag: 'rundnachricht-test',
                    mailImmer: true,
                    inApp: false,
                    knopf: 'Zur App',
                ));
                Notification::make()->title('Testmail unterwegs')->body('An '.$user->email.($data['persoenlich'] ?? false ? '. Persönliche Nachrichten gehen als Chat, die Testmail zeigt nur den Text.' : '.'))->success()->send();
            });
    }

    /** Rueckfrage vor dem Senden (wie im alten Testversand): an wen, wie viele, nicht rueckgaengig. */
    public function sendenAction(): Action
    {
        return Action::make('senden')->label('Senden')->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalHeading('Rundnachricht senden?')
            ->modalDescription(fn () => $this->vorschau())
            ->modalSubmitActionLabel('Ja, jetzt senden')
            ->action(fn () => $this->senden());
    }

    /** Text fuer die Rueckfrage: Empfaengerzahl, Kanaele, Titel. Prueft vorher das Formular. */
    public function vorschau(): string
    {
        $data = $this->form->getState();
        $ids = app(Rundsendung::class)->recipients($data['an'], $data['program_id'] ?? null, auth()->user(), $data['user_ids'] ?? []);
        $n = $ids->count();
        $wohin = ($data['persoenlich'] ?? false)
            ? 'als persönliche Nachricht ins 1:1-Gespräch'
            : 'per '.collect($data['kanaele'] ?? [])->map(fn ($k) => $k === 'push' ? 'Push/Telegram' : 'Mail')->join(' und ').(($data['mail_alle'] ?? false) ? ', Mail an alle' : '').(($data['chat'] ?? false) ? ', dazu ins Gruppengespräch' : '');
        $titel = trim((string) ($data['titel'] ?? '')) ?: mb_substr(trim((string) ($data['text'] ?? '')), 0, 60);

        return "Geht an $n Person".($n === 1 ? '' : 'en').", $wohin. «{$titel}». Das lässt sich nicht rückgängig machen."
            .(app(Notifier::class)->testMode() ? ' Testbetrieb ist an: nur freigegebene Adressen bekommen etwas.' : '');
    }

    public function senden(): void
    {
        $data = $this->form->getState();
        $eintrag = $this->entwurfId ? Eintrag::where('status', 'entwurf')->find($this->entwurfId) : null;
        $r = app(Rundsendung::class)->send($data, auth()->user(), $eintrag);

        Notification::make()
            ->title("An {$r['empfaenger']} Person".($r['empfaenger'] === 1 ? '' : 'en').' geschickt')
            ->body($r['persoenlich'] ? 'Die Nachricht steht jetzt im persönlichen Gespräch jeder Person.' : $r['erreicht'].' davon direkt erreicht (Push, Telegram oder Mail)'.($r['chat'] ? ', dazu im Gruppengespräch' : '').'. Wer keinen Kanal hat, sieht es in der App.'
                .(app(Notifier::class)->testMode() ? ' Testbetrieb ist an: nur freigegebene Adressen bekommen etwas.' : ''))
            ->success()->send();
        $this->entwurfId = null;
        $this->form->fill(array_merge(self::LEER, ['an' => $data['an'], 'program_id' => $data['program_id'] ?? null, 'user_ids' => $data['user_ids'] ?? [], 'kanaele' => $data['kanaele'] ?? ['push', 'mail']]));
    }

    /** Entwurf oder verschickte Nachricht ins Formular laden (verschickte als Vorlage, ohne Verknuepfung). */
    public function laden(int $id): void
    {
        $e = Eintrag::find($id);
        if (! $e) {
            return;
        }
        $this->entwurfId = $e->status === 'entwurf' ? $e->id : null;
        $this->form->fill(array_merge(self::LEER, $e->formular()));
    }

    public function loeschen(int $id): void
    {
        Eintrag::where('status', 'entwurf')->where('id', $id)->delete();
        if ($this->entwurfId === $id) {
            $this->entwurfId = null;
        }
    }

    public function neu(): void
    {
        $this->entwurfId = null;
        $this->form->fill(self::LEER);
    }

    public function getEntwuerfeProperty(): Collection
    {
        return Eintrag::with('user:id,name', 'program:id,title')->where('status', 'entwurf')->latest('updated_at')->get();
    }

    public function getVerschickteProperty(): Collection
    {
        return Eintrag::with('user:id,name', 'program:id,title')->where('status', 'gesendet')->latest('sent_at')->limit(30)->get();
    }
}
