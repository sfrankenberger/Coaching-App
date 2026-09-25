<?php

namespace App\Filament\Coach\Pages;

use App\Models\Membership;
use App\Models\Program;
use App\Notifications\Notifier;
use App\Notifications\Rundsendung;
use BackedEnum;
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

/** Eine Nachricht an alle oder an ein Programm: Push, Mail, auf Wunsch ins Gruppengespraech. */
class Rundnachricht extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Rundnachricht';

    protected static ?string $title = 'Rundnachricht';

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.coach.rundnachricht';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['an' => 'alle', 'kanaele' => ['push', 'mail'], 'chat' => false, 'persoenlich' => false]);
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
                Textarea::make('text')->label('Text')->required()->rows(5)->maxLength(2000)->helperText('Kurz und warm. Der Text erscheint in Push und Mail.'),
                TextInput::make('url')->label('Link (optional)')->url()->maxLength(500)->helperText('Sonst führt der Knopf auf die Startseite.'),
                CheckboxList::make('kanaele')->label('Kanäle')->options(['push' => 'Push und Telegram (wer es hat)', 'mail' => 'Mail (wer kein Push hat)'])
                    ->required(fn ($get) => ! $get('persoenlich'))->visible(fn ($get) => ! $get('persoenlich')),
            ]),
        ])->statePath('data');
    }

    public function senden(): void
    {
        $data = $this->form->getState();
        $r = app(Rundsendung::class)->send($data, auth()->user());

        Notification::make()
            ->title("An {$r['empfaenger']} Person".($r['empfaenger'] === 1 ? '' : 'en').' geschickt')
            ->body($r['persoenlich'] ? 'Die Nachricht steht jetzt im persönlichen Gespräch jeder Person.' : $r['erreicht'].' davon direkt erreicht (Push, Telegram oder Mail)'.($r['chat'] ? ', dazu im Gruppengespräch' : '').'. Wer keinen Kanal hat, sieht es in der App.'
                .(app(Notifier::class)->testMode() ? ' Testbetrieb ist an: nur freigegebene Adressen bekommen etwas.' : ''))
            ->success()->send();
        $this->form->fill(['an' => $data['an'], 'program_id' => $data['program_id'] ?? null, 'user_ids' => $data['user_ids'] ?? [], 'kanaele' => $data['kanaele'] ?? ['push', 'mail'], 'chat' => false, 'persoenlich' => false]);
    }
}
