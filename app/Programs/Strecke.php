<?php

namespace App\Programs;

use App\Auth\MagicLink;
use App\Chat\Chat;
use App\Models\Answer;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\Branding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;

/**
 * Begleitstrecke fuer Gratiskurse (wie lea-anfang-strecke und lea-anfang-abschluss): nach zwei Tagen ein
 * Anstoss mit frischem Einstiegslink, nach sieben Tagen ein letzter, dann Ruhe. Wer den Kurs durch hat,
 * bekommt die eigenen Goldnuggets per Mail, das Team einen Push. Einstellung je Programm: settings.strecke,
 * settings.strecke_gespraech (Link zum Gespraech), settings.strecke_goldnuggets (Uebungsteil, sonst Listen der letzten Einheit).
 */
class Strecke
{
    public const TAG1 = 2;

    public const TAG2 = 7;

    public function __construct(protected Notifier $notifier, protected MagicLink $magicLink, protected Chat $chat) {}

    public static function aktiv(Program $program): bool
    {
        return (bool) ($program->settings['strecke'] ?? false);
    }

    /** Taeglicher Lauf: wer haengt, bekommt Mail 1 oder Mail 2. Gibt die Zahl der Mails zurueck. */
    public function lauf(): int
    {
        $n = 0;
        foreach (Program::where('is_published', true)->where('settings->strecke', true)->get() as $program) {
            $mitglieder = ProgramMember::where('program_id', $program->id)->where('role_in_program', 'participant')->with('user')->get();
            foreach ($mitglieder as $m) {
                $stand = (array) ($m->settings['strecke'] ?? []);
                $seit = $m->joined_at ?? $m->created_at;
                if (! $m->user || ! $m->user->email || ! empty($stand['stopp']) || ! empty($stand['abschluss_at']) || ! $seit) {
                    continue;
                }
                $tage = $seit->diffInDays(now());
                if ($tage >= self::TAG2 && ! empty($stand['mail1_at']) && empty($stand['mail2_at'])) {
                    $n += (int) $this->mail($m->user, $program, $m, 2);
                } elseif ($tage >= self::TAG1 && $tage < self::TAG2 + 7 && empty($stand['mail1_at'])) {
                    $n += (int) $this->mail($m->user, $program, $m, 1);
                }
            }
        }

        return $n;
    }

    /** Naechster offener Schritt: [nummer, Unit, offen, gesamt] oder null, wenn alles erledigt ist. */
    public function naechster(User $user, Program $program): ?array
    {
        $program->loadMissing(['steps', 'units']);
        $units = $program->orderedUnits();
        $done = app(ProgressTracker::class)->completedUnitIds($user, $program);
        $offen = $units->reject(fn (Unit $u) => $done->contains($u->id));
        if ($offen->isEmpty() || $units->isEmpty()) {
            return null;
        }
        $erste = $offen->first();

        return ['nummer' => $units->search(fn (Unit $u) => $u->id === $erste->id) + 1, 'unit' => $erste, 'offen' => $offen->count(), 'gesamt' => $units->count()];
    }

    protected function mail(User $user, Program $program, ProgramMember $m, int $stufe): bool
    {
        $n = $this->naechster($user, $program);
        if (! $n) {
            return false;
        }
        $coach = app(Branding::class)->coachName();
        $angefangen = $n['nummer'] > 1 || $m->last_seen_at !== null;
        $link = $this->magicLink->create($user, route('kurse.einheit', [$program, $n['unit']]), null, 60 * 24 * 7);
        $gespraech = $this->gespraechUrl($program);
        $titel = $n['unit']->title;

        if ($stufe === 1) {
            $betreff = $angefangen ? 'Du bist bei «'.$titel.'» stehengeblieben' : 'Dein Link zu «'.$program->title.'»';
            $text = $angefangen
                ? "Du hast mit «{$program->title}» begonnen und bist bei Schritt {$n['nummer']} von {$n['gesamt']} hängengeblieben: {$titel}.\n\nDas ist kein Vorwurf. Es passiert ständig, dass etwas dazwischenkommt. Hier ist die Tür, du musst dich nicht anmelden, der Link bringt dich direkt hin.\n\nEs sind noch {$n['offen']} Schritte, etwa ".max(5, $n['offen'] * 4).' Minuten. Du kannst jederzeit wieder aufhören, alles bleibt gespeichert.'
                : "Vor zwei Tagen hast du dir «{$program->title}» geholt, drin warst du noch nicht. Vielleicht ist die Mail untergegangen, vielleicht war der Tag voll.\n\nHier ist ein frischer Link. Ein Klick, und du bist drin, ohne Passwort.\n\n{$n['gesamt']} Schritte, etwa ".max(10, $n['gesamt'] * 4).' Minuten. Du kannst jederzeit aufhören, alles bleibt gespeichert.';
            $knopf = $angefangen ? 'Weiter bei Schritt '.$n['nummer'] : 'Jetzt anfangen';
        } else {
            $betreff = 'Ein letzter Anstoss, dann lasse ich dich in Ruhe';
            $text = "Das ist meine letzte Mail zu «{$program->title}». Danach hörst du von mir nichts mehr dazu, der Kurs bleibt dir aber offen, so lange du willst.\n\n"
                .($angefangen ? "Du warst schon drin und bist bei «{$titel}» stehengeblieben. Eine halbe Stunde hat kaum jemand am Stück, aber zehn Minuten gehen fast immer." : 'Du warst noch nicht drin. Falls du es doch noch magst, hier ist die Tür.')
                .($gespraech ? "\n\nUnd falls dir das Alleindurchgehen gerade zu viel ist: wir können auch einfach reden. Eine halbe Stunde, kostenlos, kein Verkaufsgespräch." : '');
            $knopf = $angefangen ? 'Da weitermachen, wo du warst' : 'Doch noch anfangen';
        }
        $html = ($stufe === 2 && $gespraech ? '<p><a href="'.e($gespraech).'">Gespräch mit '.e($coach).' buchen</a></p>' : '')
            .'<p><a href="'.e($this->stoppUrl($user, $program)).'">Keine weiteren Erinnerungen zu diesem Kurs</a></p>';

        $report = $this->notifier->send([$user], new Nachricht(
            titel: $betreff, text: $text, url: $link, anlass: 'strecke', tag: 'strecke-'.$program->id.'-'.$stufe,
            mailImmer: true, inApp: false, knopf: $knopf, html: $html,
        ));
        if (empty($report[$user->id])) {
            return false;
        }
        $this->merken($m, ['mail'.$stufe.'_at' => now()->toIso8601String()]);

        return true;
    }

    /** Nach jedem Abhaken: ist der Kurs durch, kommen die Goldnuggets per Mail und ein Push ans Team. */
    public function pruefen(User $user, Unit $unit): void
    {
        $program = $unit->program;
        if (! $program || ! self::aktiv($program) || $this->naechster($user, $program) !== null) {
            return;
        }
        $m = ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->first();
        if (! $m || ! empty(($m->settings['strecke'] ?? [])['abschluss_at'])) {
            return;
        }
        $this->abschluss($user, $program, $m);
    }

    public function abschluss(User $user, Program $program, ProgramMember $m): void
    {
        $coach = app(Branding::class)->coachName();
        $nuggets = $this->goldnuggets($user, $program);
        $gespraech = $this->gespraechUrl($program);
        $text = "Du hast es durch: «{$program->title}». Und du hast dabei etwas gesehen, das vorher nicht da war.\n\n"
            .($nuggets->isNotEmpty() ? 'Das hier hast du aufgeschrieben. Heb es auf, nicht als Merksatz, sondern als etwas, das du selbst gesehen hast.' : 'Falls du deine Goldnuggets noch aufschreiben möchtest: der Schritt wartet auf dich, du kannst jederzeit zurück.')
            ."\n\nUnd jetzt? Du musst gar nichts. Der Kurs bleibt dir, die Sätze auch."
            .($gespraech ? "\n\nWenn beim Durchgehen aber etwas aufgetaucht ist, an dem du wirklich arbeiten willst, dann lass uns reden. Eine halbe Stunde, kostenlos, kein Verkaufsgespräch." : '');
        $html = ($nuggets->isNotEmpty() ? '<ul>'.$nuggets->map(fn ($z) => '<li>'.e($z).'</li>')->join('').'</ul>' : '')
            .($gespraech ? '<p><a href="'.e($gespraech).'">Gespräch mit '.e($coach).' buchen</a></p>' : '');
        $this->notifier->send([$user], new Nachricht(
            titel: 'Deine Goldnuggets', text: $text, url: route('kurse.show', $program), anlass: 'strecke', tag: 'strecke-'.$program->id.'-durch',
            mailImmer: true, inApp: true, knopf: 'Nochmal reinschauen', html: $html,
        ));
        $this->merken($m, ['abschluss_at' => now()->toIso8601String()]);

        $team = $this->chat->teamIds()->reject(fn ($id) => $id === $user->id);
        $this->notifier->send($team, new Nachricht(
            titel: 'Jemand hat «'.$program->title.'» durch',
            text: $user->vorname().' ist fertig mit dem Gratiskurs.'.($nuggets->isNotEmpty() ? ' Goldnuggets: '.$nuggets->take(3)->join(' · ') : ''),
            url: route('coachees.index'), anlass: 'strecke_durch', tag: 'strecke-durch-'.$user->id, mailWennKeinPush: false, knopf: 'Zur Person',
        ));
    }

    /** Die Goldnuggets: Antwort auf den eingestellten Uebungsteil, sonst alle Listen der letzten Einheit. */
    public function goldnuggets(User $user, Program $program): Collection
    {
        $program->loadMissing(['steps', 'units']);
        $schluessel = trim((string) ($program->settings['strecke_goldnuggets'] ?? ''));
        $unitIds = $program->orderedUnits()->pluck('id');
        $q = Exercise::whereIn('unit_id', $unitIds);
        $exercises = $schluessel !== ''
            ? (clone $q)->where(fn ($w) => $w->where('legacy_key', $schluessel)->orWhere('id', (int) $schluessel))->get()
            : (clone $q)->where('unit_id', $unitIds->last())->where('type', 'list')->get();
        if ($exercises->isEmpty()) {
            return collect();
        }

        return Answer::where('user_id', $user->id)->whereIn('exercise_id', $exercises->pluck('id'))->get()
            ->flatMap(fn (Answer $a) => preg_split('~\R~', Exercise::alsText($a->value['v'] ?? null)))
            ->map(fn ($z) => trim((string) $z, " \t-•"))->filter()->values();
    }

    public function gespraechUrl(Program $program): ?string
    {
        $eigen = trim((string) ($program->settings['strecke_gespraech'] ?? ''));
        if ($eigen !== '') {
            return $eigen;
        }
        $tenant = app(Branding::class)->tenant();

        return $tenant && Feature::for($tenant)->active('buchung') ? route('buchen.index') : null;
    }

    public function stoppUrl(User $user, Program $program): string
    {
        return URL::signedRoute('strecke.stopp', ['program' => $program->id, 'user' => $user->id]);
    }

    public function stopp(User $user, Program $program): void
    {
        $m = ProgramMember::where('program_id', $program->id)->where('user_id', $user->id)->first();
        if ($m) {
            $this->merken($m, ['stopp' => true]);
        }
    }

    protected function merken(ProgramMember $m, array $werte): void
    {
        $settings = $m->settings ?? [];
        $settings['strecke'] = array_merge((array) ($settings['strecke'] ?? []), $werte);
        $m->forceFill(['settings' => $settings])->saveQuietly();
    }
}
