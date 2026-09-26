<?php

namespace App\Shop;

use App\Auth\MagicLink;
use App\Enums\Role;
use App\Mail\WillkommenMail;
use App\Models\Entitlement;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Zugaenge anlegen und beenden, egal woher (Shop-Webhook, Stripe spaeter, von Hand).
 * Legt bei Bedarf die Person samt Mitgliedschaft an und schickt die Willkommensmail.
 */
class Zugang
{
    public function __construct(protected CurrentTenant $current, protected MagicLink $magicLink, protected Notifier $notifier) {}

    /** Person zur Mailadresse finden oder anlegen, Mitgliedschaft sicherstellen. Gibt [User, neu?] zurueck. */
    public function ensureUser(string $email, ?string $name = null): array
    {
        $email = Str::lower(trim($email));
        $user = User::where('email', $email)->first();
        $newUser = false;
        if (! $user) {
            $user = User::create(['name' => trim((string) $name) ?: Str::before($email, '@'), 'email' => $email]);
            $newUser = true;
        } elseif (filled($name) && $user->name === Str::before($user->email, '@')) {
            $user->forceFill(['name' => trim($name)])->save();
        }

        $membership = $user->membershipIn();
        $newMembership = false;
        if (! $membership) {
            Membership::create(['user_id' => $user->id, 'role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()]);
            $newMembership = true;
        } elseif (! $membership->isActive()) {
            $membership->forceFill(['status' => 'active'])->save();
            $newMembership = true;
        } elseif ($membership->role === Role::Guest) {
            $membership->forceFill(['role' => Role::Member->value])->save();
        }

        return [$user, $newUser || $newMembership];
    }

    /** Zugang zu einem Angebot geben (idempotent ueber Quelle und Referenz). */
    public function grant(User $user, Offer $offer, string $source, ?string $ref, ?CarbonInterface $startsAt = null, ?CarbonInterface $endsAt = null, bool $notify = true): Entitlement
    {
        $startsAt ??= now();
        if ($endsAt === null && $offer->access_days) {
            $endsAt = $startsAt->copy()->addDays((int) $offer->access_days);
        }

        $e = Entitlement::firstOrNew(['user_id' => $user->id, 'offer_id' => $offer->id, 'source' => $source, 'source_ref' => $ref]);
        $wasCurrent = $e->exists && $e->isCurrent();
        $e->fill(['status' => 'active', 'starts_at' => $startsAt, 'ends_at' => $endsAt])->save();

        if ($notify && ! $wasCurrent) {
            $this->notifier->send([$user], new Nachricht(
                titel: 'Dein Zugang ist da',
                text: $offer->title.' ist jetzt für dich freigeschaltet.',
                url: route('kurse.index'),
                anlass: 'system',
                tag: 'zugang-'.$e->id,
                knopf: 'Zu deinen Kursen',
            ));
        }

        return $e;
    }

    /** Zugang beenden (alle Zugaenge zu dieser Quelle und Referenz). */
    public function revoke(string $source, string $ref, ?CarbonInterface $endsAt = null, string $status = 'ended'): int
    {
        $n = 0;
        foreach (Entitlement::where('source', $source)->where('source_ref', $ref)->where('status', 'active')->get() as $e) {
            $e->forceFill(['status' => $status, 'ends_at' => $endsAt ?? now()])->save();
            $n++;
        }

        return $n;
    }

    /** Automatische Willkommensmail (Shop): im Testbetrieb nur an freigegebene Adressen. */
    public function welcomeAutomatic(User $user, ?Offer $offer = null): bool
    {
        if (! $this->notifier->allowedInTestMode($user)) {
            return false;
        }
        $this->welcome($user, $offer);

        return true;
    }

    /** Willkommensmail mit Anmeldelink (7 Tage gueltig). */
    public function welcome(User $user, ?Offer $offer = null): void
    {
        $url = $this->magicLink->create($user, route('home', absolute: false), null, 60 * 24 * 7);
        Mail::to($user->email, $user->name)->send(new WillkommenMail($user, $url, $offer?->title));
    }
}
