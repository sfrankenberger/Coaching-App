<?php

namespace App\Support;

use App\Models\Projekt;
use App\Models\User;
use App\Programs\ProgramAccess;
use Illuminate\Support\Collection;

/** Was das Dreipunkt-Menue je Person braucht, einmal je Anfrage geladen: Kurse mit eigenem Raum und Projekte. */
class Elemente
{
    protected array $gemeinschaft = [];

    protected array $projekte = [];

    public function __construct(protected ProgramAccess $access) {}

    /** [program_id => Titel] der Gruppenprogramme mit eigenem Raum. */
    public function gemeinschaft(User $user): array
    {
        return $this->gemeinschaft[$user->id] ??= $this->access->gemeinschaftFor($user)->pluck('title', 'id')->all();
    }

    public function projekte(User $user): Collection
    {
        if (! Funktionen::an('projekte')) {
            return collect();
        }

        return $this->projekte[$user->id] ??= Projekt::where('user_id', $user->id)->orderBy('position')->orderBy('name')->get(['id', 'name', 'farbe', 'icon']);
    }

    public function vergessen(): void
    {
        $this->gemeinschaft = [];
        $this->projekte = [];
    }
}
