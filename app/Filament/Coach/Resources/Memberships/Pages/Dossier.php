<?php

namespace App\Filament\Coach\Resources\Memberships\Pages;

use App\Chat\Chat;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Answer;
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
use Filament\Actions\Action;
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

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
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
            Action::make('mail')->label('Mail')->icon('heroicon-o-envelope')->url('mailto:'.$user->email)->openUrlInNewTab(),
            Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-device-phone-mobile')
                ->url('https://wa.me/'.preg_replace('~\D+~', '', (string) $user->phone))->openUrlInNewTab()->visible(filled($user->phone)),
            Action::make('anrufen')->label('Anrufen')->icon('heroicon-o-phone')->url('tel:'.$user->phone)->visible(filled($user->phone)),
            Action::make('bearbeiten')->label('Bearbeiten')->url(MembershipResource::getUrl('edit', ['record' => $this->record]))->color('gray'),
        ];
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

        $answers = Answer::where('user_id', $user->id)->where('shared_with_coach', true)->with('exercise.unit.program')
            ->get()->filter(fn (Answer $a) => $a->isFilled() && $a->exercise?->unit)
            ->groupBy(fn (Answer $a) => $a->exercise->unit_id);

        return [
            'person' => $user,
            'mitgliedschaft' => $this->record,
            'programme' => $programs,
            'antworten' => $answers,
            'aufgaben' => Task::where('user_id', $user->id)->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->limit(30)->get(),
            'reflexionen' => Reflection::where('user_id', $user->id)->where('visibility', '!=', 'private')->latest()->limit(10)->get(),
            'notizen' => Note::where('user_id', $user->id)->whereIn('visibility', ['coach', 'program', 'all'])->latest()->limit(20)->get(),
            'termine' => EventAttendee::where('user_id', $user->id)->with('event')->get()->filter(fn ($a) => $a->event)->sortByDesc(fn ($a) => $a->event->starts_at)->take(15),
            'einzeltermine' => Event::where('user_id', $user->id)->orderByDesc('starts_at')->limit(10)->get(),
            'push' => PushSubscription::where('user_id', $user->id)->count(),
            'telegram' => TelegramLink::where('user_id', $user->id)->where('active', true)->exists(),
        ];
    }
}
