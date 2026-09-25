<?php

namespace App\Notifications;

use App\Chat\Chat;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rundnachricht der Coachin: an alle aktiven Personen oder an ein Programm,
 * per Push/Telegram und Mail, auf Wunsch zusaetzlich als Nachricht im Gruppengespraech.
 */
class Rundsendung
{
    public function __construct(protected Notifier $notifier, protected Chat $chat) {}

    /** Empfaenger-IDs (ohne Team und ohne die Absenderin). */
    public function recipients(string $an, ?int $programId, User $von): Collection
    {
        $ids = match ($an) {
            'programm' => $programId ? ProgramMember::where('program_id', $programId)->pluck('user_id') : collect(),
            default => Membership::where('status', 'active')->whereIn('role', ['member', 'client'])->pluck('user_id'),
        };

        return $ids->unique()->reject(fn ($id) => (int) $id === $von->id)->values();
    }

    /**
     * @param  array{an: string, program_id?: int|null, titel: string, text: string, url?: string|null, kanaele?: array, chat?: bool}  $data
     * @return array{empfaenger: int, erreicht: int, chat: bool}
     */
    public function send(array $data, User $von): array
    {
        $ids = $this->recipients($data['an'] ?? 'alle', $data['program_id'] ?? null, $von);
        $kanaele = (array) ($data['kanaele'] ?? ['push', 'mail']);

        $report = $ids->isNotEmpty() ? $this->notifier->send($ids, new Nachricht(
            titel: trim($data['titel']),
            text: trim($data['text']),
            url: filled($data['url'] ?? null) ? $data['url'] : route('home'),
            anlass: 'system',
            tag: 'rundnachricht-'.now()->timestamp,
            mailWennKeinPush: in_array('mail', $kanaele, true),
            knopf: 'Zur App',
        )) : [];

        $chat = false;
        if (! empty($data['chat']) && ($data['an'] ?? '') === 'programm' && ! empty($data['program_id']) && ($program = Program::find($data['program_id']))) {
            $conv = $this->chat->groupFor($program);
            $this->chat->send($conv, $von, ['body' => trim($data['titel'])."\n\n".trim($data['text'])]);
            $chat = true;
        }

        return ['empfaenger' => $ids->count(), 'erreicht' => count(array_filter($report)), 'chat' => $chat];
    }
}
