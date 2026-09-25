<?php

namespace App\Filament\Coach\Widgets;

use App\Chat\Chat;
use App\Models\Answer;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Reflection;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Kennzahlen auf dem Dashboard der Coachin. */
class UeberblickWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $woche = now()->subDays(7);
        $ungelesen = app(Chat::class)->unreadFor(auth()->user());
        $naechster = Event::query()->where('is_published', true)->upcoming()->first();
        $geteilt = Answer::where('shared_with_coach', true)->where('updated_at', '>', $woche)->count()
            + Reflection::where('visibility', '!=', 'private')->where('shared_at', '>', $woche)->count();

        return [
            Stat::make('Aktive Personen', Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->count())
                ->description(Membership::where('last_seen_at', '>', $woche)->count().' in den letzten 7 Tagen da'),
            Stat::make('Ungelesene Nachrichten', $ungelesen)->description($ungelesen ? 'im Gespräch' : 'alles gelesen')->color($ungelesen ? 'warning' : 'success'),
            Stat::make('Nächster Termin', $naechster ? $naechster->starts_at->translatedFormat('D, j. M, H:i') : 'keiner')->description($naechster?->title ?? 'Nichts geplant'),
            Stat::make('Geteilt diese Woche', $geteilt)->description('Antworten und Reflexionen'),
            Stat::make('Aufgaben erledigt', Task::where('done_at', '>', $woche)->count())->description('in den letzten 7 Tagen'),
        ];
    }
}
