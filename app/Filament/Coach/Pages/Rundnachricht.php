<?php

namespace App\Filament\Coach\Pages;

use App\Ai\Anthropic;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Rundnachricht as Eintrag;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Newsletter\Bausteine;
use App\Notifications\Rundsendung;
use App\Support\Bildkarte;
use App\Tenancy\Branding;
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
use Illuminate\Support\Str;

/**
 * Eine Nachricht an alle oder an ein Programm: Push, Mail, auf Wunsch ins Gruppengespraech.
 * Entwuerfe lassen sich speichern und spaeter laden, eine Testmail geht an die eigene Adresse,
 * verschickte Rundnachrichten stehen unten im Protokoll. Die Mail kann aus Bausteinen bestehen
 * (Bild, Text mit fett und Listen, Knopf), ein Bild laesst sich aus dem Text erzeugen.
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

    public const LEER = ['an' => 'alle', 'program_id' => null, 'user_ids' => [], 'titel' => null, 'text' => null, 'url' => null, 'bloecke' => [], 'kanaele' => ['push', 'mail'], 'chat' => false, 'persoenlich' => false, 'mail_alle' => false];

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
            Section::make('Mail gestalten (optional)')
                ->description('Leer: die Mail zeigt Titel, Anrede und den Text von oben. Mit Bausteinen zeigt die Mail stattdessen diese Bausteine, mit Bild, fett, Listen und Knopf. Push und App zeigen immer den kurzen Text.')
                ->visible(fn ($get) => ! $get('persoenlich'))->collapsible()
                ->afterHeader([
                    Action::make('bild')->label('Bild erzeugen')->icon('heroicon-o-sparkles')->color('gray')->size('sm')
                        ->modalHeading('Bild aus dem Text erzeugen')
                        ->modalDescription('Eine Textkarte in den Farben der App: ein Satz, darunter eine kleine Zeile. Sie kommt als erster Baustein oben in die Mail.')
                        ->modalSubmitActionLabel('Erzeugen')
                        ->form([
                            Textarea::make('satz')->label('Satz auf dem Bild')->rows(3)->maxLength(160)
                                ->helperText('Leer lassen: die App schlägt aus Titel und Text einen Satz vor.'),
                            TextInput::make('unterzeile')->label('Kleine Zeile darunter')->maxLength(60)->default(fn () => app(Branding::class)->coachName()),
                        ])
                        ->action(fn (array $data) => $this->bildErzeugen($data)),
                    Action::make('textBaustein')->label('Text übernehmen')->icon('heroicon-o-arrow-down-on-square')->color('gray')->size('sm')
                        ->action(fn () => $this->textAlsBaustein()),
                ])
                ->schema([Bausteine::feld('bloecke')]),
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
        return Action::make('test')->label('Testmail')->icon('heroicon-o-envelope')->color('gray')
            ->modalHeading('Testmail schicken')
            ->modalDescription('Die Mail geht genau so raus wie an die Empfängerinnen, mit [Test] im Betreff. Nur an dich oder jemanden aus dem Team.')
            ->modalSubmitActionLabel('Schicken')
            ->form([
                Select::make('an')->label('An')->options(fn () => Membership::whereIn('role', ['owner', 'team'])->where('status', 'active')->with('user:id,name,email')->get()->filter->user->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name.' ('.$m->user->email.')'])->all())
                    ->default(fn () => auth()->id())->required()->native(false),
            ])
            ->action(function (array $arguments, array $data) {
                $an = (int) ($data['an'] ?? auth()->id());
                $data = $this->form->getState();
                $user = Membership::whereIn('role', ['owner', 'team'])->where('user_id', $an)->first()?->user ?? auth()->user();
                $text = str_replace(['{vorname}', '{name}'], [$user->vorname(), $user->name], trim($data['text']));
                $titel = trim((string) ($data['titel'] ?? '')) ?: mb_substr($text, 0, 60);
                app(Notifier::class)->send([$user->id], new Nachricht(
                    titel: $titel,
                    text: $text,
                    url: filled($data['url'] ?? null) ? $data['url'] : route('home'),
                    anlass: 'system',
                    tag: 'rundnachricht-test',
                    mailBetreff: '[Test] '.$titel,
                    mailImmer: true,
                    inApp: false,
                    knopf: 'Zur App',
                    bloecke: Eintrag::bloecke($data),
                ));
                Notification::make()->title('Testmail unterwegs')->body('An '.$user->name.', '.$user->email.($data['persoenlich'] ?? false ? '. Persönliche Nachrichten gehen als Chat, die Testmail zeigt nur den Text.' : '.'))->success()->send();
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

    /** Textkarte erzeugen und als ersten Baustein einsetzen. Ohne Satz schlaegt die KI einen vor, sonst nimmt die App den Titel. */
    public function bildErzeugen(array $data): void
    {
        $roh = $this->form->getRawState();
        $satz = trim((string) ($data['satz'] ?? '')) ?: $this->satzVorschlag((string) ($roh['titel'] ?? ''), (string) ($roh['text'] ?? ''));
        if ($satz === '') {
            Notification::make()->title('Kein Satz fürs Bild')->body('Schreib zuerst einen Titel oder einen Text, oder gib den Satz direkt ein.')->warning()->send();

            return;
        }
        try {
            $pfad = Bildkarte::erzeugen($satz, $data['unterzeile'] ?? null);
        } catch (\Throwable $e) {
            Notification::make()->title('Bild nicht erzeugt')->body($e->getMessage())->danger()->send();

            return;
        }
        $this->bausteinEinsetzen(['type' => 'bild', 'data' => ['datei' => [(string) Str::uuid() => $pfad], 'url' => null, 'link' => null, 'alt' => $satz, 'breite' => 'voll']], oben: true);
        Notification::make()->title('Bild eingesetzt')->body('«'.$satz.'» steht jetzt als erster Baustein. Du kannst den Satz ändern und das Bild neu erzeugen.')->success()->send();
    }

    /** Den kurzen Text als Textbaustein uebernehmen, damit er sich mit fett und Listen bearbeiten laesst. */
    public function textAlsBaustein(): void
    {
        $text = trim((string) ($this->form->getRawState()['text'] ?? ''));
        if ($text === '') {
            Notification::make()->title('Kein Text da')->body('Schreib zuerst den Text oben.')->warning()->send();

            return;
        }
        $this->bausteinEinsetzen(['type' => 'text', 'data' => ['html' => Bausteine::textZuHtml($text)]]);
    }

    /** Baustein in den Baukasten setzen, oben oder unten. Der Baukasten fuehrt seine Zeilen mit einer Kennung. */
    protected function bausteinEinsetzen(array $baustein, bool $oben = false): void
    {
        $bisher = array_filter((array) ($this->data['bloecke'] ?? []), fn ($b) => is_array($b) && ! empty($b['type']));
        $neu = [(string) Str::uuid() => $baustein];
        $this->data['bloecke'] = $oben ? $neu + $bisher : $bisher + $neu;
    }

    /** Ein Satz fuers Bild: die KI aus Titel und Text, ohne Schluessel oder bei Fehler der Titel. */
    protected function satzVorschlag(string $titel, string $text): string
    {
        $titel = trim($titel);
        $text = trim($text);
        if ($text !== '') {
            try {
                $r = app(Anthropic::class)->text(
                    "Titel: {$titel}\n\nText:\n{$text}",
                    'Du schreibst für eine Coachin. Aus Titel und Text einer Nachricht an ihre Teilnehmerinnen machst du einen einzigen kurzen Satz für ein Bild oben in der Mail: höchstens zwölf Wörter, warm, in der Du-Form, Schweizer Schreibweise (kein ß), kein Gedankenstrich, keine Anführungszeichen, kein Punkt am Ende. Antworte nur mit dem Satz.',
                    120,
                );
                $satz = trim(trim((string) ($r['text'] ?? '')), "\"«»'.");
                if ($satz !== '' && mb_strlen($satz) <= 160) {
                    return $satz;
                }
            } catch (\Throwable) {
                // ohne Schluessel oder bei Stoerung: Titel
            }
        }

        return $titel;
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
