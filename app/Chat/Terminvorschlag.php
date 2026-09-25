<?php

namespace App\Chat;

use App\Models\Event;
use App\Models\Message;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

/**
 * Terminvorschlag im 1:1-Gespraech (wie lea-terminvorschlag): Die Coachin schickt einige Zeiten,
 * die Person tippt eine an, daraus wird der 1:1-Termin, und im Gespraech steht die Bestaetigung.
 */
class Terminvorschlag
{
    public function __construct(protected Chat $chat, protected CurrentTenant $current) {}

    /** @param  array<int, string|\DateTimeInterface>  $zeiten */
    public function vorschlagen(User $von, User $person, array $zeiten, int $dauer, ?string $text = null): Message
    {
        $iso = collect($zeiten)->filter()->map(fn ($z) => Carbon::parse($z, config('app.timezone'))->utc())
            ->filter(fn (Carbon $z) => $z->isFuture())->sort()->unique()->values()->map->toIso8601String()->all();
        abort_if($iso === [], 422, 'Mindestens eine Zeit in der Zukunft.');

        return $this->chat->send($this->chat->directFor($person), $von, [
            'body' => filled($text) ? $text : 'Diese Zeiten hätte ich für unser nächstes Gespräch. Tipp einfach die an, die dir passt.',
            'meta' => ['vorschlaege' => $iso, 'dauer' => max(15, min(240, $dauer))],
        ]);
    }

    /** Die Person waehlt Zeit Nummer $i. Gibt den angelegten Termin zurueck. */
    public function waehlen(Message $vorschlag, User $person, int $i): Event
    {
        $conv = $vorschlag->conversation;
        abort_unless($conv && $conv->isDirect() && $conv->user_id === $person->id, 403);
        abort_if(isset($vorschlag->meta['gebucht']), 409, 'Schon gebucht.');
        $start = $vorschlag->vorschlaege()->get($i);
        abort_unless($start && $start->isFuture(), 422, 'Diese Zeit ist nicht mehr frei.');

        $tenant = $this->current->get();
        $event = Event::create([
            'title' => (string) ($tenant?->setting('termine.einzel_titel') ?: 'Einzelsitzung'),
            'type' => 'one_on_one',
            'user_id' => $person->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes((int) ($vorschlag->meta['dauer'] ?? 60)),
            'zoom_url' => $tenant?->setting('termine.einzel_zoom_url') ?: null,
            'location' => $tenant?->setting('termine.einzel_zoom_url') ? 'Online via Zoom' : null,
            'is_published' => true,
        ]);

        $vorschlag->forceFill(['meta' => ($vorschlag->meta ?? []) + ['gebucht' => ['i' => $i, 'event_id' => $event->id, 'am' => now()->toIso8601String()]]])->save();

        $this->chat->send($conv, $person, [
            'body' => 'Ich nehme '.$start->translatedFormat('l, j. F, H:i').' Uhr.',
            'ref' => ['type' => 'event', 'id' => $event->id],
        ]);

        return $event;
    }
}
