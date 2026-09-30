<?php

namespace App\Support;

use App\Chat\Chat;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use Illuminate\Support\Collection;

/** @-Erwaehnungen im Text: @Vorname trifft alle im Kurs und im Team, die so heissen. */
class Erwaehnungen
{
    public function __construct(protected Chat $chat) {}

    /** Wer im Kurs erwaehnt werden kann: [id => Name], fuer die Vorschlaege im Feld. */
    public function personen(Program $program): Collection
    {
        $ids = ProgramMember::where('program_id', $program->id)->pluck('user_id')->merge($this->chat->teamIds())->unique();

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])->mapWithKeys(fn (User $u) => [$u->id => $u->name]);
    }

    /** Ids der Personen, die im Text mit @Vorname oder @Vorname-Nachname gemeint sind. */
    public function finden(?string $text, Program $program): Collection
    {
        if (! $text || ! preg_match_all('~(?:^|[\s(])@([\p{L}][\p{L}\-]{1,})~u', $text, $m)) {
            return collect();
        }
        $woerter = collect($m[1])->map(fn ($w) => mb_strtolower($w))->unique();

        return $this->personen($program)->filter(function (string $name) use ($woerter) {
            $vorname = mb_strtolower(trim(explode(' ', trim($name))[0]));
            $ganz = mb_strtolower(str_replace(' ', '-', trim($name)));

            return $woerter->contains($vorname) || $woerter->contains($ganz);
        })->keys()->map(fn ($id) => (int) $id)->values();
    }
}
