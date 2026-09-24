<?php

namespace App\Console\Commands;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Models\Event;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\Unit;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Themenfinder per KI: Inhalte ohne Profil bekommen Themen und Kurztext.
 *   php84 artisan themen:profil lea --nur=post,episode --limit=20
 * Laeuft direkt (nicht in der Queue), damit man den Fortschritt sieht.
 */
class ThemenProfil extends Command
{
    protected $signature = 'themen:profil {tenant} {--nur= : Arten, kommagetrennt (post, episode, unit, resource, program, event)} {--limit=20} {--neu : Auch Inhalte mit Profil nochmals}';

    protected $description = 'Themen und Themenfinder-Texte per KI erzeugen';

    public function handle(CurrentTenant $current, Summarizer $summarizer): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        if (! Anthropic::configured($tenant)) {
            $this->error('Kein Anthropic-Schlüssel.');

            return self::FAILURE;
        }
        $arten = array_filter(array_map('trim', explode(',', (string) ($this->option('nur') ?: 'post,episode,unit,resource,program,event'))));
        $classes = ['post' => Post::class, 'episode' => PodcastEpisode::class, 'unit' => Unit::class, 'resource' => Resource::class, 'program' => Program::class, 'event' => Event::class];
        $limit = (int) $this->option('limit');

        return $current->run($tenant, function () use ($arten, $classes, $limit, $summarizer) {
            $n = 0;
            foreach ($arten as $art) {
                $class = $classes[$art] ?? null;
                if (! $class) {
                    continue;
                }
                $q = $class::query()->when(! $this->option('neu'), fn ($q) => $q->whereDoesntHave('finder'))->orderBy('id');
                foreach ($q->limit(max(1, $limit - $n))->get() as $model) {
                    try {
                        $p = $summarizer->finder($model);
                        $this->line("{$art} #{$model->id} ".($p ? '-> '.$model->topics()->pluck('name')->implode(', ') : 'übersprungen'));
                    } catch (\Throwable $e) {
                        $this->warn("{$art} #{$model->id}: ".$e->getMessage());
                    }
                    if (++$n >= $limit) {
                        break 2;
                    }
                }
            }
            $this->info("{$n} Inhalte bearbeitet.");

            return self::SUCCESS;
        });
    }
}
