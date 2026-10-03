<?php

namespace App\Notifications;

use App\Chat\Chat;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Rundnachricht;
use App\Models\ProgramMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rundnachricht der Coachin: an alle aktiven Personen, an ein Programm oder an Einzelne,
 * per Push/Telegram und Mail, auf Wunsch zusaetzlich im Gruppengespraech oder als
 * persoenliche Nachricht ins 1:1-Gespraech jeder Person (wie lea-coachees).
 */
class Rundsendung
{
    public function __construct(protected Notifier $notifier, protected Chat $chat) {}

    /**
     * Empfaenger-IDs: nur aktive Teilnehmerinnen (Rolle member oder client), nie das Team, nie die Absenderin.
     * an = alle | programm (ein Kurs) | begleitung (alle, die in einer 1:1 Begleitung sind oder waren) | einzelne.
     */
    public function recipients(string $an, ?int $programId, User $von, array $einzelne = []): Collection
    {
        $aktiv = Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->pluck('user_id');
        $ids = match ($an) {
            'programm' => $programId ? ProgramMember::where('program_id', $programId)->pluck('user_id')->intersect($aktiv) : collect(),
            'begleitung' => ProgramMember::whereIn('program_id', Program::where('type', 'one_on_one')->select('id'))->pluck('user_id')->intersect($aktiv),
            'einzelne' => collect($einzelne)->map(fn ($id) => (int) $id)->intersect($aktiv),
            default => $aktiv,
        };

        return $ids->unique()->reject(fn ($id) => (int) $id === $von->id)->values();
    }

    /** Kurse, die sich als Empfaengerkreis waehlen lassen: keine 1:1 Begleitung, nichts Internes, nur mit Teilnehmerinnen. */
    public static function kurse(): Collection
    {
        return Program::where('type', '!=', 'one_on_one')->where('is_internal', false)->whereHas('members')->orderBy('title')->get();
    }

    /**
     * @param  array{an: string, program_id?: int|null, user_ids?: array, titel: string, text: string, url?: string|null, kanaele?: array, chat?: bool, persoenlich?: bool, mail_alle?: bool}  $data
     * @return array{empfaenger: int, erreicht: int, chat: bool, persoenlich: int}
     */
    public function send(array $data, User $von, ?Rundnachricht $eintrag = null): array
    {
        $ids = $this->recipients($data['an'] ?? 'alle', $data['program_id'] ?? null, $von, (array) ($data['user_ids'] ?? []));
        $kanaele = (array) ($data['kanaele'] ?? ['push', 'mail']);

        // Persoenlich: jede Person bekommt die Nachricht in ihr 1:1-Gespraech, mit ihrem Vornamen.
        // Die Benachrichtigung kommt dann vom Gespraech, nicht doppelt.
        if (! empty($data['persoenlich'])) {
            $n = 0;
            foreach (User::whereIn('id', $ids)->get() as $user) {
                $text = str_replace(['{vorname}', '{name}'], [$user->vorname(), $user->name], trim($data['text']));
                $this->chat->send($this->chat->directFor($user), $von, ['body' => $text]);
                $n++;
            }

            return $this->protokoll($data, $von, $eintrag, ['empfaenger' => $ids->count(), 'erreicht' => $n, 'chat' => false, 'persoenlich' => $n]);
        }

        $report = $ids->isNotEmpty() ? $this->notifier->send($ids, new Nachricht(
            titel: trim($data['titel']),
            text: trim($data['text']),
            url: filled($data['url'] ?? null) ? $data['url'] : route('home'),
            anlass: 'system',
            tag: 'rundnachricht-'.now()->timestamp,
            mailWennKeinPush: in_array('mail', $kanaele, true),
            mailImmer: ! empty($data['mail_alle']),
            knopf: 'Zur App',
            bloecke: Rundnachricht::bloecke($data),
        )) : [];

        $chat = false;
        if (! empty($data['chat']) && ($data['an'] ?? '') === 'programm' && ! empty($data['program_id']) && ($program = Program::find($data['program_id']))) {
            $conv = $this->chat->groupFor($program);
            $this->chat->send($conv, $von, ['body' => trim($data['titel'])."\n\n".trim($data['text'])]);
            $chat = true;
        }

        return $this->protokoll($data, $von, $eintrag, ['empfaenger' => $ids->count(), 'erreicht' => count(array_filter($report)), 'chat' => $chat, 'persoenlich' => 0]);
    }

    /** Verschicktes festhalten: aus dem Entwurf wird der Eintrag, sonst ein neuer. */
    protected function protokoll(array $data, User $von, ?Rundnachricht $eintrag, array $r): array
    {
        $eintrag ??= new Rundnachricht;
        $eintrag->fill(Rundnachricht::ausFormular($data) + ['user_id' => $eintrag->user_id ?? $von->id]);
        $eintrag->forceFill(['status' => 'gesendet', 'empfaenger' => $r['empfaenger'], 'erreicht' => $r['erreicht'], 'sent_at' => now()])->save();

        return $r + ['id' => $eintrag->id];
    }
}
