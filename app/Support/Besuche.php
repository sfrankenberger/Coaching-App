<?php

namespace App\Support;

use App\Content\Inhalte;
use App\Models\Question;
use App\Models\User;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use Illuminate\Support\Carbon;

/**
 * Neu-Punkte im Menue (wie lea-neu): was seit dem letzten Besuch eines Bereichs dazugekommen ist.
 * Der Besuch steht in memberships.settings.besuche.{bereich}, ohne Besuch zaehlen die letzten 14 Tage.
 */
class Besuche
{
    public const BEREICHE = ['impulse', 'material', 'community', 'termine'];

    protected array $cache = [];

    public function merken(User $user, string $bereich): void
    {
        $m = $user->membershipIn();
        if (! $m || ! in_array($bereich, self::BEREICHE, true)) {
            return;
        }
        $settings = $m->settings ?? [];
        $settings['besuche'][$bereich] = now()->toIso8601String();
        $m->forceFill(['settings' => $settings])->saveQuietly();
        unset($this->cache[$user->id]);
    }

    public function seit(User $user, string $bereich): Carbon
    {
        $wann = $user->membershipIn()?->setting("besuche.$bereich");
        $grenze = now()->subDays(14);

        return $wann ? max(Carbon::parse($wann), $grenze) : $grenze;
    }

    /** [bereich => Anzahl neu], nur Bereiche mit etwas Neuem. */
    public function zaehler(User $user): array
    {
        return $this->cache[$user->id] ??= array_filter([
            'impulse' => app(Inhalte::class)->postsQuery($user)->published()->where('published_at', '>', $this->seit($user, 'impulse'))->count()
                + app(Inhalte::class)->episodesQuery($user)->where('published_at', '>', $this->seit($user, 'impulse'))->count(),
            'material' => app(Begleitung::class)->resourcesQuery($user)->where('resources.created_at', '>', $this->seit($user, 'material'))->count(),
            'community' => Question::whereIn('program_id', app(ProgramAccess::class)->programIdsFor($user))->where('visibility', 'program')
                ->where('user_id', '!=', $user->id)->where('created_at', '>', $this->seit($user, 'community'))->count(),
            'termine' => app(Begleitung::class)->eventsQuery($user)->where('created_at', '>', $this->seit($user, 'termine'))->where('starts_at', '>', now())->count(),
        ]);
    }
}
