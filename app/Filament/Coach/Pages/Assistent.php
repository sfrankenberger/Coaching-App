<?php

namespace App\Filament\Coach\Pages;

use App\Ai\Anthropic;
use App\Ai\Assistent as AssistentDienst;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Filament\Coach\Resources\Materials\MaterialResource;
use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use App\Filament\Coach\Resources\Posts\PostResource;
use App\Filament\Coach\Resources\Programs\ProgramResource;
use App\Filament\Coach\Resources\Tools\ToolResource;
use App\Models\Event;
use App\Models\FinderProfile;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Resource;
use App\Models\Sammlung;
use App\Models\Tool;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Der digitale Assistent der Coachin: Fragen (wo finde ich was, Betriebsfragen zu Menschen),
 * Themen pruefen, Werkzeuge, Geteiltes (Sammlungen aus dem Nachschlagen).
 */
class Assistent extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Assistent';

    protected static ?string $title = 'Dein digitaler Assistent';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.coach.assistent';

    #[Url(as: 'r')]
    public string $reiter = 'fragen';

    public string $frage = '';

    public ?array $antwort = null;

    #[Url(as: 'typ')]
    public string $typ = 'alle';

    public function reiterWaehlen(string $reiter): void
    {
        $this->reiter = in_array($reiter, ['fragen', 'themen', 'werkzeuge', 'geteiltes'], true) ? $reiter : 'fragen';
        $this->resetPage();
    }

    public function fragen(?string $text = null): void
    {
        if ($text !== null) {
            $this->frage = $text;
        }
        $this->frage = trim($this->frage);
        if (mb_strlen($this->frage) < 3) {
            $this->addError('frage', 'Stell mir eine ganze Frage.');

            return;
        }
        $this->resetErrorBag();
        $this->antwort = app(AssistentDienst::class)->antwort($this->frage, auth()->user());
    }

    public function passt(int $id): void
    {
        FinderProfile::whereKey($id)->update(['is_checked' => true]);
    }

    public function typWaehlen(string $typ): void
    {
        $this->typ = $typ;
        $this->resetPage();
    }

    /** Inhalte mit KI-Themen, die noch niemand bestaetigt hat. */
    public function offeneThemen(): LengthAwarePaginator
    {
        $typen = ['alle' => 'Alles', 'post' => 'Impulse', 'episode' => 'Podcast', 'unit' => 'Lektionen', 'step' => 'Wochen', 'program' => 'Kurse', 'resource' => 'Material', 'event' => 'Termine', 'tool' => 'Werkzeuge'];

        return FinderProfile::query()->where('is_checked', false)
            ->when(isset($typen[$this->typ]) && $this->typ !== 'alle', fn ($q) => $q->where('profilable_type', $this->typ))
            ->with('profilable')->orderByDesc('generated_at')->orderByDesc('id')->paginate(15, pageName: 'themenSeite');
    }

    public function stand(): array
    {
        return ['fertig' => FinderProfile::where('is_checked', true)->count(), 'gesamt' => FinderProfile::count()];
    }

    /** Wo laesst sich der Inhalt bearbeiten? */
    public function bearbeitenUrl(?Model $m): ?string
    {
        try {
            return match (true) {
                $m instanceof Post => PostResource::getUrl('edit', ['record' => $m]),
                $m instanceof PodcastEpisode => PodcastEpisodeResource::getUrl('edit', ['record' => $m]),
                $m instanceof Resource => MaterialResource::getUrl('edit', ['record' => $m]),
                $m instanceof Event => EventResource::getUrl('edit', ['record' => $m]),
                $m instanceof Tool => ToolResource::getUrl('edit', ['record' => $m]),
                $m instanceof Program => ProgramResource::getUrl('edit', ['record' => $m]),
                $m instanceof Unit, $m instanceof ProgramStep => $m->program ? ProgramResource::getUrl('edit', ['record' => $m->program]) : null,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function werkzeuge(): Collection
    {
        return Tool::query()->orderBy('position')->orderBy('title')->get();
    }

    public function sammlungen(): Collection
    {
        $s = Sammlung::query()->with('author:id,name')->orderByDesc('id')->limit(40)->get();
        $ids = $s->flatMap(fn ($x) => array_merge((array) $x->recipients, (array) $x->seen))->unique()->filter();
        $namen = User::whereIn('id', $ids)->pluck('name', 'id');

        return $s->each(function (Sammlung $x) use ($namen) {
            $seen = array_map('intval', (array) $x->seen);
            $x->setAttribute('empfaenger', collect((array) $x->recipients)->map(fn ($id) => ($namen[$id] ?? '?').(in_array((int) $id, $seen, true) ? ' (gesehen)' : ''))->implode(', '));
        });
    }

    protected function getViewData(): array
    {
        return [
            'vorschlaege' => AssistentDienst::VORSCHLAEGE,
            'ki' => Anthropic::configured(app(CurrentTenant::class)->get()),
            'typen' => ['alle' => 'Alles', 'post' => 'Impulse', 'episode' => 'Podcast', 'unit' => 'Lektionen', 'step' => 'Wochen', 'program' => 'Kurse', 'resource' => 'Material', 'event' => 'Termine', 'tool' => 'Werkzeuge'],
            'toolIndex' => ToolResource::getUrl(),
            'toolNeu' => ToolResource::getUrl('create'),
        ];
    }
}
