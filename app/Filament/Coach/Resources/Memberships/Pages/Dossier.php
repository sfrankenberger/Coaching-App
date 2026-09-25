<?php

namespace App\Filament\Coach\Resources\Memberships\Pages;

use App\Chat\Chat;
use App\Coach\Kommentare;
use App\Coach\Lage;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Answer;
use App\Models\CoachNote;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Note;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\TelegramLink;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use App\Shop\Zugang;
use App\Support\Telefon;
use App\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Dossier einer Person: Zugang, Programme mit Fortschritt, geteilte Antworten,
 * Aufgaben, Reflexionen, Notizen, Termine, Kontaktknoepfe und der Weg ins Gespraech.
 */
class Dossier extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MembershipResource::class;

    protected string $view = 'filament.coach.dossier';

    /** Neue private Notiz der Coachin. */
    public string $notiz = '';

    /** Antworten auf Geteiltes, Schluessel "typ-id". */
    public array $antwort = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function notizSpeichern(): void
    {
        $this->validate(['notiz' => ['required', 'string', 'max:10000']]);
        CoachNote::create(['user_id' => $this->record->user_id, 'author_id' => auth()->id(), 'body' => trim($this->notiz)]);
        $this->notiz = '';
        Notification::make()->title('Notiz gespeichert')->success()->send();
    }

    public function notizAnheften(int $id): void
    {
        $n = CoachNote::where('user_id', $this->record->user_id)->findOrFail($id);
        $n->forceFill(['is_pinned' => ! $n->is_pinned])->save();
    }

    public function notizLoeschen(int $id): void
    {
        CoachNote::where('user_id', $this->record->user_id)->findOrFail($id)->delete();
    }

    public function antworten(string $typ, int $id): void
    {
        $text = trim((string) ($this->antwort[$typ.'-'.$id] ?? ''));
        if ($text === '') {
            return;
        }
        $k = app(Kommentare::class);
        $item = $k->finden($typ, $id);
        abort_unless($item && $item->user_id === $this->record->user_id, 404);
        $k->schreiben(auth()->user(), $item, $text);
        unset($this->antwort[$typ.'-'.$id]);
        Notification::make()->title($this->record->user->vorname().' bekommt Bescheid')->success()->send();
    }

    public function getTitle(): string
    {
        return $this->record->user->name;
    }

    protected function getHeaderActions(): array
    {
        $user = $this->record->user;

        return [
            Action::make('gespraech')->label('Gespräch')->icon('heroicon-o-chat-bubble-left-right')
                ->url(fn () => route('gespraech.show', app(Chat::class)->directFor($user))),
            Action::make('zeiten')->label('Zeiten vorschlagen')->icon('heroicon-o-calendar-days')
                ->modalHeading('Zeiten vorschlagen')->modalDescription($user->vorname().' sieht die Zeiten im Gespräch und tippt eine an. Daraus wird der Termin.')
                ->schema([
                    \Filament\Forms\Components\Repeater::make('zeiten')->label('Zeiten')->simple(
                        \Filament\Forms\Components\DateTimePicker::make('start')->required()->native(false)->seconds(false)->displayFormat('D d.m.Y H:i')->minDate(now())
                    )->minItems(1)->maxItems(6)->default([null])->addActionLabel('Weitere Zeit'),
                    \Filament\Forms\Components\TextInput::make('dauer')->label('Dauer (Minuten)')->numeric()->default(60)->minValue(15)->maxValue(240)->required(),
                    \Filament\Forms\Components\Textarea::make('text')->label('Nachricht dazu')->rows(2)
                        ->default('Hallo '.$user->vorname().', diese Zeiten hätte ich für unser nächstes Gespräch. Tipp einfach die an, die dir passt.'),
                ])
                ->action(function (array $data) use ($user) {
                    app(\App\Chat\Terminvorschlag::class)->vorschlagen(auth()->user(), $user, collect($data['zeiten'])->all(), (int) $data['dauer'], $data['text'] ?? null);
                    Notification::make()->title('Zeiten sind im Gespräch mit '.$user->vorname())->success()->send();
                }),
            Action::make('vorbereitung')->label(fn () => $this->vorbereitung() ? 'Vorbereitung neu' : 'Vorbereitung (KI)')->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn () => \App\Ai\Anthropic::configured(app(CurrentTenant::class)->get()))
                ->requiresConfirmation()->modalHeading('Vorbereitung auf das Gespräch erstellen?')
                ->modalDescription('Aus dem, was '.$user->vorname().' geteilt hat, deinen Notizen und dem Gespräch. Dauert etwa eine Minute, du bekommst Bescheid.')
                ->action(function () {
                    \App\Models\AiSummary::updateOrCreate(['summarizable_type' => 'membership', 'summarizable_id' => $this->record->id, 'kind' => 'vorbereitung'], ['status' => 'pending', 'error' => null]);
                    \App\Jobs\VorbereitungErstellen::dispatch(app(CurrentTenant::class)->id(), $this->record->id, auth()->id());
                    Notification::make()->title('Läuft. Du bekommst Bescheid, sobald es fertig ist.')->success()->send();
                }),
            Action::make('mail')->label('Mail')->icon('heroicon-o-envelope')->url('mailto:'.$user->email)->openUrlInNewTab(),
            Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-device-phone-mobile')
                ->url(Telefon::whatsapp($user->phone, app(CurrentTenant::class)->get()))->openUrlInNewTab()->visible(filled($user->phone)),
            Action::make('anrufen')->label('Anrufen')->icon('heroicon-o-phone')->url('tel:'.$user->phone)->visible(filled($user->phone)),
            Action::make('einladen')->label('Einladung')->icon('heroicon-o-paper-airplane')->color('gray')->requiresConfirmation()
                ->modalHeading('Willkommensmail mit Anmeldelink schicken?')->modalDescription('Der Link gilt sieben Tage.')
                ->action(function () use ($user) {
                    app(Zugang::class)->welcome($user);
                    Notification::make()->title('Einladung an '.$user->email.' geschickt')->success()->send();
                }),
            Action::make('bearbeiten')->label('Bearbeiten')->url(MembershipResource::getUrl('edit', ['record' => $this->record]))->color('gray'),
        ];
    }

    /** Gespeicherte Vorbereitung, drei Tage gueltig. */
    public function vorbereitung(): ?\App\Models\AiSummary
    {
        return \App\Models\AiSummary::where('summarizable_type', 'membership')->where('summarizable_id', $this->record->id)
            ->where('kind', 'vorbereitung')->where('updated_at', '>=', now()->subDays(3))->first();
    }

    protected function getViewData(): array
    {
        $user = $this->record->user;
        $access = app(ProgramAccess::class);
        $tracker = app(ProgressTracker::class);

        $programs = $access->programsFor($user)->map(function ($p) use ($user, $tracker) {
            $p->setAttribute('stand', $tracker->summary($user, $p));
            $p->setAttribute('mitglied', ProgramMember::where('program_id', $p->id)->where('user_id', $user->id)->first());

            return $p;
        });

        $answers = Answer::where('user_id', $user->id)->where('shared_with_coach', true)->with(['exercise.unit.program', 'comments.user'])
            ->get()->filter(fn (Answer $a) => $a->isFilled() && $a->exercise?->unit)
            ->groupBy(fn (Answer $a) => $a->exercise->unit_id);

        return [
            'person' => $user,
            'mitgliedschaft' => $this->record,
            'programme' => $programs,
            'antworten' => $answers,
            // Private Aufgaben bleiben privat, sie werden nur gezaehlt (wie im alten Bereich)
            'aufgaben' => Task::where('user_id', $user->id)->where(fn ($q) => $q->where('visibility', '!=', 'private')->orWhereNotNull('assigned_by'))
                ->with('comments.user')->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->limit(30)->get(),
            'privateAufgaben' => Task::where('user_id', $user->id)->where('visibility', 'private')->whereNull('assigned_by')->count(),
            'reflexionen' => Reflection::where('user_id', $user->id)->where('visibility', '!=', 'private')->with('comments.user')->latest()->limit(10)->get(),
            'notizen' => Note::where('user_id', $user->id)->whereIn('visibility', ['coach', 'program', 'all'])->with('comments.user')->latest()->limit(20)->get(),
            'vorbereitung' => $this->vorbereitung(),
            'coachNotizen' => CoachNote::where('user_id', $user->id)->with('author')->orderByDesc('is_pinned')->latest()->get(),
            'lage' => app(Lage::class)->fuer($this->record),
            'termine' => EventAttendee::where('user_id', $user->id)->with('event')->get()->filter(fn ($a) => $a->event)->sortByDesc(fn ($a) => $a->event->starts_at)->take(15),
            'einzeltermine' => Event::where('user_id', $user->id)->orderByDesc('starts_at')->limit(10)->get(),
            'push' => PushSubscription::where('user_id', $user->id)->count(),
            'telegram' => TelegramLink::where('user_id', $user->id)->where('active', true)->exists(),
        ];
    }
}
