<?php

namespace App\Ai\Werkzeuge;

use App\Models\CoachNote;
use App\Models\Offer;
use App\Models\User;
use App\Programs\ProgramAccess;
use App\Shop\Zugang;

class ZugangGeben extends Werkzeug
{
    public function __construct(protected Zugang $zugang, protected ProgramAccess $access) {}

    public function name(): string
    {
        return 'zugang_geben';
    }

    public function beschreibung(): string
    {
        return 'Etwas verkaufen oder schenken: ein Angebot für eine Person freischalten (Programme dazu), mit Laufzeit, zusätzlichen 1:1-Sitzungen und Notiz. Die Person bekommt Bescheid.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + [
            'offer_id' => ['type' => 'integer', 'description' => 'aus angebote'],
            'angebot' => ['type' => 'string', 'description' => 'oder der Titel des Angebots, wenn eindeutig'],
            'tage' => ['type' => 'integer', 'description' => 'Laufzeit in Tagen, sonst wie im Angebot'],
            'sitzungen' => ['type' => 'integer', 'description' => 'zusätzliche 1:1-Sitzungen'],
            'preis' => ['type' => 'string', 'description' => 'nur als Notiz, z. B. 1200 CHF'],
            'notiz' => ['type' => 'string'],
            'willkommensmail' => ['type' => 'boolean', 'description' => 'Willkommensmail mit Anmeldelink statt der kurzen Mitteilung'],
        ]);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        $offer = ! empty($args['offer_id']) ? Offer::where('is_active', true)->find((int) $args['offer_id']) : null;
        if (! $offer && filled($args['angebot'] ?? null)) {
            $t = Offer::where('is_active', true)->where('title', 'like', '%'.$args['angebot'].'%')->get();
            abort_if($t->count() > 1, 409, 'Mehrere Angebote passen: '.$t->pluck('title')->join(', '));
            $offer = $t->first();
        }
        abort_unless($offer, 404, 'Angebot nicht gefunden. Nutze angebote für die Liste.');
        $user = $m->user;
        $ende = ! empty($args['tage']) ? now()->addDays((int) $args['tage']) : ($offer->access_days ? now()->addDays((int) $offer->access_days) : null);
        $mail = (bool) ($args['willkommensmail'] ?? false);
        $this->zugang->grant($user, $offer, 'manual', 'mcp-'.$von->id.'-'.now()->format('YmdHis'), now(), $ende, ! $mail);
        foreach ($offer->programs as $p) {
            $pm = $this->access->join($user, $p);
            if ($p->type === 'one_on_one' && ! empty($args['sitzungen'])) {
                $pm->forceFill(['settings' => array_merge($pm->settings ?? [], ['sitzungen_extra' => (int) ($pm->settings['sitzungen_extra'] ?? 0) + (int) $args['sitzungen']])])->save();
            }
        }
        $zeilen = array_filter(['Verkauft: '.$offer->title, filled($args['preis'] ?? null) ? 'Preis: '.$args['preis'] : null, ! empty($args['sitzungen']) ? 'inkl. '.$args['sitzungen'].' 1:1-Sitzungen' : null, $ende ? 'bis '.$ende->translatedFormat('j. F Y') : null, filled($args['notiz'] ?? null) ? trim($args['notiz']) : null]);
        CoachNote::create(['user_id' => $user->id, 'author_id' => $von->id, 'body' => implode("\n", $zeilen)]);
        if ($mail) {
            $this->zugang->welcome($user, $offer);
        }

        return ['ok' => true, 'angebot' => $offer->title, 'bis' => $ende?->toDateString(), 'programme' => $offer->programs->pluck('title')->all()] + $this->kurz($m);
    }
}
