<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\TelegramLink;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;

/**
 * Ein zentraler Dienst entscheidet pro Person und Anlass den Kanal:
 * Push, wenn vorhanden, sonst Mail. Telegram zusaetzlich, wenn verbunden.
 * Abendmail als Sammelmail nur an Personen ohne Push und nur bei Neuem.
 *
 * Anlaesse: chat | termin | aufzeichnung | aufgabe | geteilt | material | impuls | system
 * Schalter je Person (memberships.settings.notifications): termine, abendmail, aufgaben
 */
class Notifier
{
    public function __construct(protected CurrentTenant $current) {}

    /**
     * Schickt eine Nachricht an Personen (IDs oder Modelle).
     * Gibt zurueck, wer ueber welchen Kanal erreicht wurde.
     */
    public function send(iterable $users, Nachricht $nachricht): array
    {
        $report = [];
        foreach ($this->users($users) as $user) {
            $channels = $this->channelsFor($user, $nachricht);
            if ($channels === []) {
                $report[$user->id] = [];

                continue;
            }
            $user->notify(new AppNotification($nachricht, $channels, $this->current->id()));
            $report[$user->id] = $channels;
        }

        return $report;
    }

    /** Kanaele fuer eine Person und einen Anlass. */
    public function channelsFor(User $user, Nachricht $nachricht): array
    {
        $membership = $user->membershipIn();
        if (! $membership || ! $membership->isActive()) {
            return [];
        }
        if (! $this->wants($membership, $nachricht->anlass)) {
            return [];
        }
        if (! $this->allowedInTestMode($user)) {
            return [];
        }

        $channels = [];
        $push = PushSubscription::where('user_id', $user->id)->exists();
        $telegram = TelegramLink::where('user_id', $user->id)->where('active', true)->exists();

        if ($push) {
            $channels[] = WebPushChannel::class;
        }
        if ($telegram) {
            $channels[] = TelegramChannel::class;
        }
        if (! $push && ! $telegram && $nachricht->mailWennKeinPush && filled($user->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function hasPushOrTelegram(User $user): bool
    {
        return PushSubscription::where('user_id', $user->id)->exists()
            || TelegramLink::where('user_id', $user->id)->where('active', true)->exists();
    }

    /**
     * Testbetrieb (settings.notifications.test_only): solange die App parallel zum alten
     * System laeuft, gehen Benachrichtigungen nur an die freigegebenen Adressen
     * (settings.notifications.test_emails). So bekommt niemand etwas doppelt.
     */
    public function allowedInTestMode(User $user): bool
    {
        $tenant = $this->current->get();
        if (! $tenant || ! $tenant->setting('notifications.test_only')) {
            return true;
        }
        $erlaubt = array_map(fn ($e) => strtolower(trim((string) $e)), (array) $tenant->setting('notifications.test_emails', []));

        return in_array(strtolower((string) $user->email), $erlaubt, true);
    }

    public function testMode(): bool
    {
        return (bool) $this->current->get()?->setting('notifications.test_only');
    }

    /** Schalter der Person: Termin-Erinnerungen, Abendmail, Aufgaben-Erinnerungen. */
    public function wants(Membership $membership, string $anlass): bool
    {
        return match ($anlass) {
            'termin' => (bool) $membership->setting('notifications.termine', true),
            'aufgabe_erinnerung' => (bool) $membership->setting('notifications.aufgaben', true),
            'abendmail' => (bool) $membership->setting('notifications.abendmail', true),
            default => true,
        };
    }

    protected function users(iterable $users): Collection
    {
        $items = collect($users);
        $ids = $items->map(fn ($u) => $u instanceof User ? $u->id : (int) $u)->unique()->filter();

        return User::whereIn('id', $ids)->get();
    }
}
