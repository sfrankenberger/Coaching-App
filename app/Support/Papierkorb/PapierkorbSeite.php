<?php

namespace App\Support\Papierkorb;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Gemeinsame Seite "Papierkorb" fuer Coach-Bereich und Plattform. Endgueltig loeschen nur in der Plattform. */
abstract class PapierkorbSeite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Papierkorb';

    protected static ?string $title = 'Papierkorb';

    protected static ?int $navigationSort = 96;

    protected string $view = 'filament.papierkorb.seite';

    /** Filter nach Typ (Morph-Alias), leer = alles */
    public ?string $typ = null;

    abstract protected function plattform(): bool;

    public function istPlattform(): bool
    {
        return $this->plattform();
    }

    public function typen(): array
    {
        return app(Papierkorb::class)->typen();
    }

    public function zeilen(): Collection
    {
        return app(Papierkorb::class)->eintraege($this->plattform(), $this->typ ?: null);
    }

    public function fristTage(): int
    {
        return Papierkorb::FRIST_TAGE;
    }

    public function wiederherstellen(string $typ, int $id): void
    {
        $m = app(Papierkorb::class)->finden($typ, $id, $this->plattform());
        if (! $m) {
            Notification::make()->title('Eintrag nicht gefunden')->danger()->send();

            return;
        }
        app(Papierkorb::class)->wiederherstellen($m);
        Notification::make()->title('Wiederhergestellt')->body($m->protokollTitel())->success()->send();
    }

    public function endgueltig(string $typ, int $id): void
    {
        abort_unless($this->plattform(), 403);
        $m = app(Papierkorb::class)->finden($typ, $id, true);
        if (! $m) {
            Notification::make()->title('Eintrag nicht gefunden')->danger()->send();

            return;
        }
        $titel = $m->protokollTitel();
        app(Papierkorb::class)->endgueltig($m);
        Notification::make()->title('Endgültig gelöscht')->body($titel)->warning()->send();
    }
}
