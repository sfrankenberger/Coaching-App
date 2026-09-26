<?php

namespace App\Import\WordPress;

use App\Models\Bookmark;
use App\Models\Event;
use App\Models\FinderProfile;
use App\Models\Membership;
use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Import 4: Impulse (Blog-Beitraege mit Sichtbarkeit "Free"), Podcastfolgen (CPT podcast
 * samt KI-Aufbereitung), Themen (Taxonomie thema) mit Zuordnung an Kurse, Module,
 * Lektionen, Material, Beitraege und Folgen, die Themenfinder-Texte (lea_th_*) und
 * die Merklisten (usermeta lea_gemerkt).
 */
class InhalteImport
{
    public array $stats = ['beitraege' => 0, 'folgen' => 0, 'themen' => 0, 'zuordnungen' => 0, 'profile' => 0, 'merker' => 0, 'hinweise' => []];

    protected array $config;

    protected array $topicMap = [];

    protected array $postMap = [];

    protected array $episodeMap = [];

    protected $report = null;

    public function __construct(protected Tenant $tenant, protected WordPressSource $source, protected bool $dryRun = false)
    {
        $this->config = array_replace_recursive([
            'website' => (string) $this->tenant->setting('website'),
            'uploads_url' => null,
            'post_visibility' => ['taxonomy' => 'sichtbarkeit', 'slug' => 'free'],
            'news_categories' => [],            // Kategorien, die "Neuigkeit" statt "Impuls" sind
            'post_exclude_ids' => [],
            'post_exclude_categories' => [],     // z. B. Kategorie fuer Kampagnen-Mails
            'club_visibility_slug' => null,      // Sichtbarkeit "Club-intern" -> Neuigkeit fuer das Club-Programm
            'course_news_meta' => null,          // Kurs-Meta mit der Sichtbarkeits-ID fuer Kurs-Neuigkeiten
            'podcast_series_taxonomy' => 'series',
            'topic_taxonomies' => ['thema', 'podcast_thema'],
            'finder_meta' => ['summary' => 'lea_th_kurz', 'helps' => 'lea_th_hilft', 'keywords' => 'lea_th_stich', 'checked' => 'lea_th_geprueft'],
            'bookmark_meta' => 'lea_gemerkt',
            'bookmark_prefixes' => ['ressource' => 'resource', 'termin' => 'event', 'lektion' => 'unit', 'post' => 'post', 'impuls' => 'post', 'blog' => 'post', 'podcast' => 'episode', 'kurs' => 'program', 'modul' => 'step'],
        ], (array) $this->tenant->setting('import.wordpress', []));
    }

    public function run(?callable $report = null): array
    {
        $this->report = $report;
        $this->importTopics();
        $this->importPosts();
        $this->importPodcast();
        $this->tagPrograms();
        $this->importBookmarks();

        return $this->stats;
    }

    protected function say(string $line): void
    {
        if ($this->report) {
            ($this->report)($line);
        }
    }

    /* ---------- Themen ---------- */

    protected function importTopics(): void
    {
        foreach ((array) $this->config['topic_taxonomies'] as $tax) {
            foreach ($this->source->terms($tax) as $termId => $t) {
                $this->stats['themen']++;
                if ($this->dryRun) {
                    continue;
                }
                $topic = Topic::where('legacy_id', (string) $termId)->first()
                    ?? Topic::where('slug', Str::slug($t['name']))->first()
                    ?? new Topic;
                $topic->fill(['legacy_id' => $topic->legacy_id ?: (string) $termId, 'name' => $t['name'], 'description' => $topic->description ?: ($t['description'] ?: null)]);
                if (! $topic->exists) {
                    $topic->slug = Str::slug($t['name']);
                }
                $topic->save();
                $this->topicMap[$termId] = $topic->id;
            }
        }
    }

    /** Themen und Themenfinder-Text an ein Modell haengen. */
    protected function tag(Model $model, int $wpId, array $objectTerms): void
    {
        $ids = [];
        foreach ($objectTerms[$wpId] ?? [] as $termId) {
            if ($tid = $this->topicMap[$termId] ?? null) {
                $ids[] = $tid;
            }
        }
        if ($ids !== []) {
            $before = $model->topics()->count();
            $model->topics()->syncWithoutDetaching(array_unique($ids));
            $this->stats['zuordnungen'] += max(0, $model->topics()->count() - $before);
        }

        $m = $this->config['finder_meta'];
        $summary = trim((string) $this->source->meta($wpId, $m['summary']));
        $helps = trim((string) $this->source->meta($wpId, $m['helps']));
        $keywords = $this->source->meta($wpId, $m['keywords']);
        if ($summary === '' && $helps === '') {
            return;
        }
        FinderProfile::updateOrCreate(
            ['profilable_type' => $model->getMorphClass(), 'profilable_id' => $model->id],
            ['summary' => $summary ?: null, 'helps' => $helps ?: null, 'keywords' => is_array($keywords) ? array_values($keywords) : null, 'is_checked' => (bool) $this->source->meta($wpId, $m['checked']), 'generated_at' => now()],
        );
        $this->stats['profile']++;
    }

    protected function allObjectTerms(): array
    {
        $out = [];
        foreach ((array) $this->config['topic_taxonomies'] as $tax) {
            foreach ($this->source->objectTerms($tax) as $pid => $terms) {
                $out[$pid] = array_merge($out[$pid] ?? [], $terms);
            }
        }

        return $out;
    }

    /* ---------- Impulse ---------- */

    protected function importPosts(): void
    {
        $vis = $this->config['post_visibility'];
        $visTerms = $this->source->terms($vis['taxonomy']);
        $visTermId = null;
        foreach ($visTerms as $id => $t) {
            if ($t['slug'] === $vis['slug']) {
                $visTermId = $id;
            }
        }
        $visObjects = $visTermId ? $this->source->objectTerms($vis['taxonomy']) : [];

        // Neuigkeiten nur fuer ein Programm: Sichtbarkeit "Club-intern" oder die Sichtbarkeit eines Kurses
        $programFor = [];
        if ($slug = $this->config['club_visibility_slug']) {
            $clubTerm = collect($visTerms)->search(fn ($t) => $t['slug'] === $slug);
            $club = Program::where('type', 'club')->orderBy('id')->first();
            if ($clubTerm !== false && $club) {
                $programFor[(int) $clubTerm] = $club->id;
            }
        }
        if ($metaKey = $this->config['course_news_meta']) {
            foreach (Program::whereNotNull('legacy_id')->get(['id', 'legacy_id']) as $prog) {
                $term = (int) $this->source->meta((int) $prog->legacy_id, $metaKey, 0);
                if ($term) {
                    $programFor[$term] = $prog->id;
                }
            }
        }

        $cats = $this->source->terms('category');
        $catObjects = $this->source->objectTerms('category');
        $objectTerms = $this->allObjectTerms();
        $exclude = array_map('intval', (array) $this->config['post_exclude_ids']);
        $excludeCats = array_map('intval', (array) $this->config['post_exclude_categories']);
        $news = array_map('intval', (array) $this->config['news_categories']);

        foreach ($this->source->posts('post', ['publish']) as $post) {
            $id = (int) $post->ID;
            if (in_array($id, $exclude, true) || array_intersect($catObjects[$id] ?? [], $excludeCats) !== []) {
                continue;
            }
            $hat = $visObjects[$id] ?? [];
            $frei = $visTermId && in_array($visTermId, $hat, true);
            $programId = null;
            foreach ($hat as $t) {
                $programId ??= $programFor[(int) $t] ?? null;
            }
            if ($visTermId && ! $frei && ! $programId) {
                continue;
            }
            $postCats = $catObjects[$id] ?? [];
            $catNames = array_values(array_filter(array_map(fn ($c) => ($cats[$c]['slug'] ?? '') === 'uncategorized' ? null : ($cats[$c]['name'] ?? null), $postCats)));
            $type = (array_intersect($postCats, $news) !== [] || (! $frei && $programId)) ? 'neuigkeit' : 'impuls';

            $this->say("Beitrag #{$id} {$post->post_title} -> {$type}");
            $this->stats['beitraege']++;
            if ($this->dryRun) {
                continue;
            }

            $p = Post::firstOrNew(['legacy_id' => (string) $id]);
            $p->fill([
                'type' => $type,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'excerpt' => trim(strip_tags((string) $post->post_excerpt)) ?: null,
                'body' => WordPressSource::autop($post->post_content),
                'image_url' => $this->thumbnail($id),
                'url' => $this->config['website'] ? rtrim($this->config['website'], '/').'/?p='.$id : null,
                'source' => 'wordpress',
                'categories' => $catNames ?: null,
                'visibility' => $frei || ! $programId ? 'members' : 'program',
                'program_id' => $frei ? null : $programId,
                'published_at' => Carbon::parse($post->post_date, $this->tenant->timezone ?: config('app.timezone'))->utc(),
                'is_published' => true,
                'notified_at' => $p->notified_at ?? now(),   // alte Beitraege nicht nachmelden
            ]);
            if (! $p->exists) {
                $p->slug = Post::uniqueSlug($post->post_name ?: $post->post_title);
            }
            $p->save();
            $this->postMap[$id] = $p->id;
            $this->tag($p, $id, $objectTerms);
        }
    }

    protected function thumbnail(int $postId): ?string
    {
        $att = (int) $this->source->meta($postId, '_thumbnail_id');
        if (! $att) {
            return null;
        }
        $file = (string) $this->source->meta($att, '_wp_attached_file');
        if ($file === '') {
            $guid = $this->source->post($att)?->guid ?? null;

            return $guid ?: null;
        }
        $base = $this->config['uploads_url'] ?: rtrim((string) $this->config['website'], '/').'/wp-content/uploads';

        return rtrim($base, '/').'/'.$file;
    }

    /* ---------- Podcast ---------- */

    protected function importPodcast(): void
    {
        $series = $this->source->terms($this->config['podcast_series_taxonomy']);
        $seriesObjects = $this->source->objectTerms($this->config['podcast_series_taxonomy']);
        $objectTerms = $this->allObjectTerms();

        foreach ($this->source->posts('podcast', ['publish']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $audio = (string) ($m('audio_file') ?: $m('enclosure'));
            $audio = trim(explode("\n", $audio)[0]);
            if ($audio === '') {
                continue;
            }
            $showId = ($seriesObjects[$id] ?? [null])[0];
            $show = $showId && isset($series[$showId]) ? $series[$showId]['name'] : 'Podcast';

            $this->say("Folge #{$id} {$post->post_title} ({$show})");
            $this->stats['folgen']++;
            if ($this->dryRun) {
                continue;
            }

            $guid = (string) ($m('lea_feed_guid') ?: $m('ssp_original_guid') ?: 'wp-'.$id);
            $e = PodcastEpisode::where('legacy_id', (string) $id)->first() ?? PodcastEpisode::firstOrNew(['guid' => $guid]);
            $transcript = (string) $m('lea_transkript_html');
            if ($transcript === '') {
                $roh = WordPressSource::unserialize($m('lea_transkript_roh'));
                $json = is_string($roh) ? json_decode($roh, true) : null;
                $transcript = is_array($json) ? (string) ($json['text'] ?? '') : '';
            }
            $date = $m('date_recorded') ?: $post->post_date;
            $e->fill([
                'legacy_id' => (string) $id,
                'guid' => $guid,
                'show' => $show,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'episode_number' => ($n = (int) $m('itunes_episode_number')) ?: null,
                'excerpt' => trim(strip_tags((string) $post->post_excerpt)) ?: null,
                'body' => WordPressSource::autop($post->post_content),
                'audio_url' => $audio,
                'image_url' => $this->thumbnail($id),
                'url' => $m('lea_feed_link') ?: ($this->config['website'] ? rtrim($this->config['website'], '/').'/?p='.$id : null),
                'duration_seconds' => PodcastEpisode::parseDuration((string) $m('duration')),
                'published_at' => Carbon::parse($date, $this->tenant->timezone ?: config('app.timezone'))->utc(),
                'transcript' => $transcript ?: null,
                'chapters' => $this->json($m('lea_kapitel')),
                'faq' => $this->json($m('lea_faq')),
                'summary' => trim((string) $m('lea_zusammenfassung')) ?: null,
                'keywords' => $this->json($m('lea_schlagworte')),
                'is_published' => true,
            ]);
            if (! $e->exists) {
                $e->slug = Str::slug($post->post_name ?: $post->post_title);
            }
            $e->save();
            $this->episodeMap[$id] = $e->id;
            $this->tag($e, $id, $objectTerms);
        }
    }

    protected function json(mixed $raw): ?array
    {
        $raw = WordPressSource::unserialize($raw);
        if (is_array($raw)) {
            return array_values($raw);
        }
        $j = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($j) ? array_values($j) : null;
    }

    /* ---------- Themen an Programmen, Schritten, Einheiten, Material, Terminen ---------- */

    protected function tagPrograms(): void
    {
        if ($this->dryRun) {
            return;
        }
        $objectTerms = $this->allObjectTerms();
        $byLegacy = fn ($query) => $query->whereNotNull('legacy_id')->get()->filter(fn ($m) => ctype_digit((string) $m->legacy_id));
        foreach ([Program::query(), ProgramStep::query(), Unit::query(), Resource::query(), Event::query()] as $q) {
            foreach ($byLegacy($q) as $model) {
                $wpId = (int) $model->legacy_id;
                if (isset($objectTerms[$wpId]) || $this->source->meta($wpId, $this->config['finder_meta']['summary'])) {
                    $this->tag($model, $wpId, $objectTerms);
                }
            }
        }
    }

    /* ---------- Merklisten ---------- */

    protected function importBookmarks(): void
    {
        $userMap = Membership::query()->whereNotNull('legacy_id')->pluck('user_id', 'legacy_id');
        $maps = [
            'resource' => Resource::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'event' => Event::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'unit' => Unit::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'program' => Program::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'step' => ProgramStep::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'post' => Post::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
            'episode' => PodcastEpisode::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id'),
        ];
        foreach ($this->source->userMetaByKey($this->config['bookmark_meta']) as $wpUid => $raw) {
            $uid = $userMap[(string) $wpUid] ?? null;
            $keys = WordPressSource::unserialize($raw);
            if (! $uid || ! is_array($keys)) {
                continue;
            }
            foreach ($keys as $key) {
                if (! preg_match('~^([a-z_]+)-(\d+)$~', (string) $key, $mm)) {
                    continue;
                }
                $type = $this->config['bookmark_prefixes'][$mm[1]] ?? null;
                $id = $type ? ($maps[$type][$mm[2]] ?? null) : null;
                if (! $id) {
                    continue;
                }
                $this->stats['merker']++;
                if (! $this->dryRun) {
                    Bookmark::firstOrCreate(['user_id' => $uid, 'bookmarkable_type' => $type, 'bookmarkable_id' => $id]);
                }
            }
        }
    }
}
