<?php

namespace App\Import\WordPress;

use App\Chat\Chat;
use App\Models\Comment;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\JournalEntry;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Note;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reaction;
use App\Models\Reflection;
use App\Models\Resource;
use App\Models\Resourceable;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import 3: Termine, Material, Aufgaben, Notizen, Reflexionen, Journal, Chats
 * und die Wochenstruktur der Hybrid-Kurse (aus den Gruppencalls).
 *
 * Voraussetzung: Import 1 (Personen) und Import 2 (Programme) sind gelaufen,
 * denn alles haengt ueber legacy_id an Personen, Programmen, Schritten und Einheiten.
 */
class BegleitungImport
{
    public array $stats = ['termine' => 0, 'teilnahmen' => 0, 'material' => 0, 'zuordnungen' => 0, 'aufgaben' => 0, 'notizen' => 0, 'reflexionen' => 0, 'journal' => 0, 'kommentare' => 0, 'fragen' => 0, 'gespraeche' => 0, 'nachrichten' => 0, 'wochen' => 0, 'hinweise' => []];

    protected array $config;

    protected array $userMap = [];

    protected array $programMap = [];

    protected array $stepMap = [];

    protected array $unitMap = [];

    /** WordPress-Post-ID => [typ, id] fuer Verweise (chat_ref, Kommentare) */
    protected array $elementMap = [];

    protected $report = null;

    public function __construct(protected Tenant $tenant, protected WordPressSource $source, protected bool $dryRun = false)
    {
        $this->config = array_replace_recursive([
            'owner_ids' => [],
            'team_roles' => ['administrator'],
            'relations' => ['course_modules' => 9, 'module_units' => 10, 'course_events' => 19, 'module_events' => 20, 'unit_events' => 21, 'course_resources' => 16, 'module_resources' => 17, 'unit_resources' => 12, 'event_resources' => 18, 'unit_tasks' => 38],
            'uploads_dir' => null,          // Ordner wp-content/uploads auf dem Server (Dateien kopieren)
            'uploads_url' => null,          // Basis-URL der Uploads
            'event_timestamps_are_local' => true,
            'meta' => ['attended' => 'lea_live_dabei', 'watched' => 'lea_angeschaut', 'foreign_tasks_done' => 'lea_af_fremd_fertig', 'chat_seen' => 'lea_ch_gesehen_'],
        ], (array) $this->tenant->setting('import.wordpress', []));
    }

    public function run(?callable $report = null): array
    {
        $this->report = $report;
        $this->userMap = Membership::query()->whereNotNull('legacy_id')->pluck('user_id', 'legacy_id')->mapWithKeys(fn ($u, $l) => [(int) $l => (int) $u])->all();
        $this->programMap = Program::query()->whereNotNull('legacy_id')->get()->filter(fn ($p) => ctype_digit((string) $p->legacy_id))->keyBy(fn ($p) => (int) $p->legacy_id)->all();
        $this->stepMap = ProgramStep::query()->whereNotNull('legacy_id')->get()->filter(fn ($s) => ctype_digit((string) $s->legacy_id))->keyBy(fn ($s) => (int) $s->legacy_id)->all();
        $this->unitMap = Unit::query()->whereNotNull('legacy_id')->get()->filter(fn ($u) => ctype_digit((string) $u->legacy_id))->keyBy(fn ($u) => (int) $u->legacy_id)->all();

        if ($this->userMap === [] || $this->programMap === []) {
            $this->hint('Erst Personen und Programme importieren (--only=users,programs).');
        }

        $this->importEvents();
        $this->importResources();
        $this->importTasks();
        $this->importNotes();
        $this->importReflections();
        $this->importJournal();
        $this->importQuestions();
        $this->importComments();
        $this->importChats();
        $this->importWeeks();

        return $this->stats;
    }

    protected function say(string $line): void
    {
        if ($this->report) {
            ($this->report)($line);
        }
    }

    protected function hint(string $line): void
    {
        $this->stats['hinweise'][] = $line;
        $this->say('! '.$line);
    }

    /* ---------- Termine ---------- */

    protected function importEvents(): void
    {
        $attended = $this->userListMeta($this->config['meta']['attended']);
        $watched = $this->userListMeta($this->config['meta']['watched']);

        foreach ($this->source->posts('termin', ['publish', 'private', 'draft']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $start = (int) $m('termin_start');
            if (! $start) {
                continue;
            }
            $art = (string) $m('termin_art', 'call');
            $person = (int) $m('nvc_person');
            $userId = $person ? ($this->userMap[$person] ?? null) : null;
            $type = match (true) {
                $art === 'reflexion' => 'reflection_day',
                $art === 'fragen' => 'question_day',
                $person > 0 => 'one_on_one',
                default => 'group_call',
            };

            $programId = null;
            foreach ($this->source->parents($this->config['relations']['course_events'], $id) as $kid) {
                if ($p = $this->programMap[$kid] ?? null) {
                    $programId = $p->id;
                    break;
                }
            }
            $stepId = null;
            foreach ($this->source->parents($this->config['relations']['module_events'], $id) as $mid) {
                if ($s = $this->stepMap[$mid] ?? null) {
                    $stepId = $s->id;
                    $programId ??= $s->program_id;
                    break;
                }
            }
            $unitId = null;
            foreach ($this->source->parents($this->config['relations']['unit_events'], $id) as $lid) {
                if ($u = $this->unitMap[$lid] ?? null) {
                    $unitId = $u->id;
                    $programId ??= $u->program_id;
                    break;
                }
            }
            if ($person && ! $userId) {
                $this->hint("Termin #{$id}: Person {$person} nicht importiert, uebersprungen");

                continue;
            }

            $this->say("Termin #{$id} {$post->post_title} -> {$type}");
            $this->stats['termine']++;
            if ($this->dryRun) {
                continue;
            }

            // saveQuietly umgeht die Beobachter (keine Aufzeichnungs-Meldungen), darum tenant_id selbst setzen
            $event = Event::firstOrNew(['legacy_id' => (string) $id]);
            $event->fill([
                'tenant_id' => $this->tenant->id,
                'program_id' => $programId,
                'step_id' => $stepId,
                'unit_id' => $unitId,
                'user_id' => $userId,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'description' => WordPressSource::autop($post->post_content),
                'type' => $type,
                'starts_at' => $this->ts($start),
                'ends_at' => ($e = (int) $m('termin_ende')) ? $this->ts($e) : null,
                'all_day' => in_array($type, Event::ALL_DAY_TYPES, true),
                'location' => $m('termin_ort') ?: null,
                'zoom_url' => $m('zoom_link') ?: null,
                'recording_url' => $m('recording_url') ?: null,
                'recording_duration' => $m('recording_dauer') ?: null,
                'transcript' => $m('recording_abschrift') ?: null,
                'summary' => is_string($m('ki_zusammenfassung')) ? $m('ki_zusammenfassung') : null,
                'is_published' => $post->post_status === 'publish',
                'recording_notified_at' => $m('recording_url') ? now() : null,
                'settings' => array_filter(['zugriffsart' => $m('zugriffsart'), 'nvc_art' => $m('nvc_art')]),
            ]);
            // Kein Nachholen alter Erinnerungen
            if (! $event->exists) {
                $event->reminded_day_at = now();
                $event->reminded_hour_at = now();
            }
            $event->saveQuietly();
            $this->keepWpTimes($event, $post);
            $this->elementMap[$id] = ['event', $event->id];

            // Absagen, live dabei, Aufzeichnung gesehen
            foreach ((array) WordPressSource::unserialize($m('lea_nicht_dabei')) as $wp) {
                $this->attendee($event, (int) $wp, 'declined');
            }
            foreach ($attended[$id] ?? [] as $wp) {
                $this->attendee($event, $wp, 'attended');
            }
            foreach ($watched[$id] ?? [] as $wp) {
                $this->attendee($event, $wp, 'watched', false);
            }
        }
    }

    protected function attendee(Event $event, int $wpUid, string $status, bool $override = true): void
    {
        $uid = $this->userMap[$wpUid] ?? null;
        if (! $uid) {
            return;
        }
        $row = EventAttendee::firstOrNew(['event_id' => $event->id, 'user_id' => $uid]);
        if (! $row->exists || $override || $row->status === 'invited') {
            // Wann genau abgesagt oder geschaut wurde, weiss WordPress nicht: Zeitpunkt des Termins
            $wann = $event->starts_at->lt(now()) ? $event->starts_at : $event->created_at;
            $row->status = $status;
            $row->attended_at ??= $wann;
            $row->timestamps = false;
            $row->created_at ??= $wann;
            $row->updated_at = $wann;
            $row->save();
            $this->stats['teilnahmen']++;
        }
    }

    /** usermeta mit Listen von Post-IDs umdrehen: [post_id => [wp_uid, ...]] */
    protected function userListMeta(string $key): array
    {
        $out = [];
        foreach ($this->source->userMetaByKey($key) as $wpUid => $raw) {
            foreach ((array) WordPressSource::unserialize($raw) as $pid) {
                $out[(int) $pid][] = (int) $wpUid;
            }
        }

        return $out;
    }

    /* ---------- Material ---------- */

    protected function importResources(): void
    {
        $typeMap = ['pdf' => 'pdf', 'audio' => 'audio', 'video' => 'video', 'podcast' => 'podcast', 'link' => 'link', 'text' => 'text', 'bild' => 'image', 'image' => 'image', 'docx' => 'pdf'];
        $rel = $this->config['relations'];

        foreach ($this->source->posts('ressource', ['publish', 'private', 'draft']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $url = (string) ($m('ressource_datei') ?: $m('ressource_url'));
            $type = $typeMap[(string) $m('ressource_typ')] ?? 'link';
            if ($type === 'link' && preg_match('~\.(pdf|docx?)(\?|$)~i', $url)) {
                $type = 'pdf';
            }

            $this->say("Material #{$id} {$post->post_title} -> {$type}");
            $this->stats['material']++;
            if ($this->dryRun) {
                continue;
            }

            $resource = Resource::firstOrNew(['legacy_id' => (string) $id]);
            $filePath = $resource->file_path ?: $this->copyUpload($url, 'material');
            $resource->fill([
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'type' => $type,
                'url' => $url ?: null,
                'file_path' => $filePath,
                'file_name' => $m('ressource_dateiname') ?: ($filePath ? basename($filePath) : null),
                'description' => trim((string) $m('ressource_beschreibung')) ?: null,
                'duration' => $m('ressource_dauer') ?: null,
                'image_url' => $m('ressource_bild') ?: null,
                'body' => WordPressSource::autop($m('ressource_text') ?: $post->post_content),
                'is_archived' => (bool) $m('nur_archiv') || $post->post_status !== 'publish',
            ])->save();
            $this->keepWpTimes($resource, $post);
            $this->elementMap[$id] = ['resource', $resource->id];

            foreach ($this->source->parents($rel['course_resources'], $id) as $kid) {
                if ($p = $this->programMap[$kid] ?? null) {
                    $this->link($resource, 'program', $p->id);
                }
            }
            foreach ($this->source->parents($rel['module_resources'], $id) as $mid) {
                if ($s = $this->stepMap[$mid] ?? null) {
                    $this->link($resource, 'step', $s->id);
                }
            }
            foreach ($this->source->parents($rel['unit_resources'], $id) as $lid) {
                if ($u = $this->unitMap[$lid] ?? null) {
                    $this->link($resource, 'unit', $u->id);
                }
            }
            foreach ($this->source->parents($rel['event_resources'], $id) as $tid) {
                if (($e = $this->elementMap[$tid] ?? null) && $e[0] === 'event') {
                    $this->link($resource, 'event', $e[1]);
                }
            }
        }
    }

    protected function link(Resource $resource, string $type, int $id): void
    {
        $row = Resourceable::firstOrCreate(['resource_id' => $resource->id, 'resourceable_type' => $type, 'resourceable_id' => $id]);
        if ($row->wasRecentlyCreated) {
            $this->stats['zuordnungen']++;
        }
    }

    /* ---------- Aufgaben ---------- */

    protected function importTasks(): void
    {
        $foreignDone = $this->userListMeta($this->config['meta']['foreign_tasks_done']);
        $coachIds = $this->coachWpIds();
        $rel = $this->config['relations'];

        foreach ($this->source->posts('aufgabe', ['publish', 'private']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $authorWp = (int) $post->post_author;
            $author = $this->userMap[$authorWp] ?? null;
            if (! $author) {
                continue;
            }
            $sicht = (string) $m('el_sicht', 'privat');
            $programId = ($p = $this->programMap[(int) $m('af_kurs')] ?? $this->programMap[(int) $m('el_kurs')] ?? null) ? $p->id : null;
            $stepId = ($s = $this->stepMap[(int) $m('af_modul')] ?? null) ? $s->id : null;
            $unitId = null;
            foreach ($this->source->parents($rel['unit_tasks'], $id) as $lid) {
                if ($u = $this->unitMap[$lid] ?? null) {
                    $unitId = $u->id;
                    $programId ??= $u->program_id;
                    break;
                }
            }
            $faellig = (string) $m('af_faellig');
            $dueAt = $faellig === '' ? null : (is_numeric($faellig) ? date('Y-m-d', (int) $faellig) : (strtotime($faellig) ? date('Y-m-d', strtotime($faellig)) : null));
            $erledigt = (int) $m('af_erledigt');

            $base = [
                'tenant_id' => $this->tenant->id,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'body' => trim(strip_tags((string) $post->post_content)) ?: null,
                'program_id' => $programId,
                'step_id' => $stepId,
                'unit_id' => $unitId,
                'due_at' => $dueAt,
                'due_time' => preg_match('~^\d{2}:\d{2}$~', (string) $m('af_zeit')) ? $m('af_zeit') : null,
                'is_daily' => (bool) $m('af_taeglich'),
                'visibility' => $this->visibility($sicht),
                'is_pinned' => (bool) $m('el_pin'),
            ];

            $this->stats['aufgaben']++;
            $this->say("Aufgabe #{$id} {$base['title']}");
            if ($this->dryRun) {
                continue;
            }

            // Von der Coachin an den Kurs: je Teilnehmerin eine eigene Aufgabe
            $isCoachTask = in_array($authorWp, $coachIds, true) && in_array($sicht, ['kurs', 'alle'], true) && $programId;
            if ($isCoachTask) {
                $members = Program::find($programId)?->members()->pluck('user_id') ?? collect();
                foreach ($members as $uid) {
                    $task = Task::firstOrNew(['legacy_id' => $id.'-'.$uid]);
                    $task->fill($base + ['user_id' => $uid, 'assigned_by' => $author, 'source' => 'coach', 'visibility' => 'coach']);
                    $wpUid = array_search($uid, $this->userMap, true);
                    $task->done_at ??= in_array($id, array_map(fn ($x) => $x, array_keys(array_filter($foreignDone, fn ($users) => in_array($wpUid, $users, true)))), true) ? now() : null;
                    $task->saveQuietly();
                }
                $this->elementMap[$id] = ['task', Task::where('legacy_id', $id.'-'.$members->first())->value('id') ?? 0];

                continue;
            }

            $task = Task::firstOrNew(['legacy_id' => (string) $id]);
            $task->fill($base + ['user_id' => $author, 'source' => $unitId ? 'program' : 'manual']);
            $task->done_at = $erledigt ? Carbon::createFromTimestampUTC($erledigt) : $task->done_at;
            $task->saveQuietly();
            $this->elementMap[$id] = ['task', $task->id];
        }
    }

    /* ---------- Notizen ---------- */

    protected function importNotes(): void
    {
        foreach ($this->source->posts('notiz', ['publish', 'private']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $author = $this->userMap[(int) $post->post_author] ?? null;
            if (! $author) {
                continue;
            }
            $body = trim(strip_tags((string) $post->post_content));
            if ($url = $m('notiz_url')) {
                $body = trim($body."\n\n".$url);
            }
            $this->stats['notizen']++;
            if ($this->dryRun) {
                continue;
            }
            $note = Note::firstOrNew(['legacy_id' => (string) $id]);
            $note->fill([
                'user_id' => $author,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8') ?: null,
                'body' => $body ?: html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'visibility' => $this->visibility((string) $m('el_sicht', 'privat')),
                'program_id' => ($p = $this->programMap[(int) $m('notiz_kurs')] ?? $this->programMap[(int) $m('el_kurs')] ?? null) ? $p->id : null,
                'is_pinned' => (bool) $m('el_pin'),
            ])->save();
            $note->timestamps = false;
            $note->forceFill(['created_at' => $this->lokal($post->post_date), 'updated_at' => $this->lokal($post->post_modified ?: $post->post_date)])->saveQuietly();
            $this->elementMap[$id] = ['note', $note->id];
        }
    }

    /* ---------- Reflexionen ---------- */

    protected function importReflections(): void
    {
        foreach ($this->source->posts('reflexion', ['publish', 'private']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $author = $this->userMap[(int) $post->post_author] ?? null;
            if (! $author) {
                continue;
            }
            $this->stats['reflexionen']++;
            if ($this->dryRun) {
                continue;
            }
            $sicht = (string) $m('el_sicht', $m('refl_an_lea') ? 'lea' : 'privat');
            $visibility = $this->visibility($sicht);
            $woche = (int) $m('refl_woche');
            $r = Reflection::firstOrNew(['legacy_id' => (string) $id]);
            $r->fill([
                'user_id' => $author,
                'program_id' => ($p = $this->programMap[(int) $m('el_kurs')] ?? null) ? $p->id : null,
                'week_label' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8') ?: ($woche ? 'Woche '.date('W', $woche) : null),
                'went_well' => trim((string) $m('refl_gut')) ?: null,
                'challenges' => trim((string) $m('refl_schwer')) ?: null,
                'focus' => trim((string) $m('refl_fokus')) ?: null,
                'visibility' => $visibility,
                'shared_at' => $visibility !== 'private' ? ($r->shared_at ?? $this->lokal($post->post_modified ?: $post->post_date)) : null,
            ])->save();
            $r->forceFill(['created_at' => $this->lokal($post->post_date)])->saveQuietly();
            $this->elementMap[$id] = ['reflection', $r->id];
        }
    }

    /* ---------- Journal ---------- */

    protected function importJournal(): void
    {
        foreach ($this->source->posts('journal', ['publish', 'private']) as $post) {
            $id = (int) $post->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);
            $author = $this->userMap[(int) $post->post_author] ?? null;
            if (! $author) {
                continue;
            }
            $this->stats['journal']++;
            if ($this->dryRun) {
                continue;
            }
            $faellig = (string) $m('journal_faellig');
            $j = JournalEntry::firstOrNew(['legacy_id' => (string) $id]);
            $j->fill([
                'user_id' => $author,
                'program_id' => ($p = $this->programMap[(int) $m('journal_kurs')] ?? $this->programMap[(int) $m('el_kurs')] ?? null) ? $p->id : null,
                'type' => (string) $m('journal_typ', 'entry') ?: 'entry',
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8') ?: null,
                'body' => trim(strip_tags((string) $post->post_content)) ?: null,
                'url' => $m('journal_url') ?: null,
                'due_at' => $faellig !== '' && strtotime($faellig) ? date('Y-m-d', strtotime($faellig)) : null,
                'is_daily' => (bool) $m('journal_taeglich'),
                'visibility' => $this->visibility((string) $m('el_sicht', 'privat')),
                'settings' => array_filter(['projekt' => $m('journal_projekt'), 'schritt' => $m('journal_schritt')]),
            ])->save();
            $j->forceFill(['created_at' => $this->lokal($post->post_date)])->saveQuietly();
            $this->elementMap[$id] = ['journal', $j->id];
        }
    }

    /* ---------- Kommentare und Reaktionen an Elementen ---------- */

    /** Fragen aus dem Kursraum (CPT frage), Antworten kommen ueber importComments. */
    protected function importQuestions(): void
    {
        foreach ($this->source->posts('frage', ['publish']) as $post) {
            $id = (int) $post->ID;
            $meta = $this->source->postMeta($id);
            $programId = Program::where('legacy_id', (string) ($meta['frage_kurs'] ?? ''))->value('id');
            $uid = $this->userMap[(int) $post->post_author] ?? null;
            if (! $programId || ! $uid || ($meta['frage_ist_chat'] ?? '') === '1') {
                continue;
            }
            $this->stats['fragen']++;
            if ($this->dryRun) {
                continue;
            }
            $status = (string) ($meta['frage_status'] ?? 'offen');
            $q = Question::firstOrNew(['legacy_id' => (string) $id]);
            $q->fill([
                'program_id' => $programId,
                'user_id' => $uid,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                'body' => trim(strip_tags((string) $post->post_content)) ?: null,
                'status' => array_key_exists($status, Question::STATUS) ? $status : 'offen',
                'visibility' => ($meta['frage_sicht'] ?? '') === 'lea' ? 'coach' : 'program',
            ]);
            $q->save();
            $this->keepWpTimes($q, $post);
            $this->elementMap[$id] = ['question', $q->id];
        }
    }

    protected function importComments(): void
    {
        if ($this->dryRun || ! $this->source->hasTable('comments')) {
            return;
        }
        $ids = array_keys(array_filter($this->elementMap, fn ($e) => in_array($e[0], ['task', 'note', 'reflection', 'journal', 'question'], true)));
        if ($ids === []) {
            return;
        }
        foreach ($this->source->db()->table('comments')->whereIn('comment_post_ID', $ids)->where('comment_approved', '1')->orderBy('comment_ID')->get() as $c) {
            [$type, $elId] = $this->elementMap[(int) $c->comment_post_ID];
            $uid = $this->userMap[(int) $c->user_id] ?? null;
            if (! $uid || ! $elId || trim((string) $c->comment_content) === '') {
                continue;
            }
            $comment = Comment::firstOrNew(['legacy_id' => (string) $c->comment_ID]);
            $comment->fill(['user_id' => $uid, 'commentable_type' => $type, 'commentable_id' => $elId, 'body' => trim($c->comment_content)])->save();
            $comment->forceFill(['created_at' => $this->lokal($c->comment_date)])->saveQuietly();
            $this->stats['kommentare']++;
        }
        foreach ($ids as $wpId) {
            [$type, $elId] = $this->elementMap[$wpId];
            $reaktionen = WordPressSource::unserialize($this->source->meta($wpId, 'el_reaktionen'));
            if (! is_array($reaktionen) || ! $elId) {
                continue;
            }
            foreach ($reaktionen as $emoji => $users) {
                foreach ((array) $users as $wpUid) {
                    if (($uid = $this->userMap[(int) $wpUid] ?? null) && array_key_exists($emoji, Reaction::EMOJIS)) {
                        Reaction::firstOrCreate(['user_id' => $uid, 'reactable_type' => $type, 'reactable_id' => $elId, 'emoji' => $emoji]);
                    }
                }
            }
        }
    }

    /* ---------- Chats (1:1) ---------- */

    protected function importChats(): void
    {
        if (! $this->source->hasTable('comments')) {
            return;
        }
        $chat = app(Chat::class);
        $seen = $this->source->userMetaLike($this->config['meta']['chat_seen']);

        foreach ($this->source->posts('chat', ['publish', 'private']) as $post) {
            $id = (int) $post->ID;
            $owner = $this->userMap[(int) $post->post_author] ?? null;
            if (! $owner) {
                continue;
            }
            $this->stats['gespraeche']++;
            if ($this->dryRun) {
                continue;
            }
            $user = User::find($owner);
            $conv = $chat->directFor($user);
            if (! $conv->legacy_id) {
                $conv->forceFill(['legacy_id' => (string) $id])->save();
            }

            $comments = $this->source->db()->table('comments')->where('comment_post_ID', $id)->where('comment_approved', '1')->orderBy('comment_ID')->get();
            foreach ($comments as $c) {
                $from = $this->userMap[(int) $c->user_id] ?? null;
                if (! $from) {
                    continue;
                }
                $cm = $this->source->db()->table('commentmeta')->where('comment_id', $c->comment_ID)->pluck('meta_value', 'meta_key')->all();
                $msg = Message::firstOrNew(['legacy_id' => (string) $c->comment_ID]);
                $isNew = ! $msg->exists;
                $msg->fill([
                    'tenant_id' => $this->tenant->id,
                    'conversation_id' => $conv->id,
                    'user_id' => $from,
                    'body' => trim((string) $c->comment_content) ?: null,
                    'audio_seconds' => isset($cm['chat_audio_sek']) ? (int) $cm['chat_audio_sek'] : null,
                    'transcript' => $cm['chat_transkript'] ?? null,
                    'source' => ($cm['lea_ch_quelle'] ?? '') === 'telegram' ? 'telegram' : 'app',
                    'nudged_at' => now(),
                ]);
                if ($isNew) {
                    if (! empty($cm['chat_audio'])) {
                        $msg->audio_path = $this->copyAttachment((int) $cm['chat_audio'], 'chat/'.$conv->id);
                    }
                    if (! empty($cm['chat_datei'])) {
                        $msg->attachment_path = $this->copyAttachment((int) $cm['chat_datei'], 'chat/'.$conv->id);
                        $msg->attachment_name = $msg->attachment_path ? basename($msg->attachment_path) : null;
                    }
                    if (! empty($cm['chat_ref']) && ($ref = $this->elementMap[(int) $cm['chat_ref']] ?? null) && $ref[1]) {
                        $msg->ref_type = $ref[0];
                        $msg->ref_id = $ref[1];
                    }
                }
                $msg->saveQuietly();
                $msg->forceFill(['created_at' => $this->lokal($c->comment_date), 'updated_at' => $this->lokal($c->comment_date)])->saveQuietly();
                $this->stats['nachrichten']++;

                $reaktionen = WordPressSource::unserialize($cm['lea_ch_reaktionen'] ?? null);
                if (is_array($reaktionen)) {
                    foreach ($reaktionen as $emoji => $users) {
                        foreach ((array) $users as $wpUid) {
                            if (($uid = $this->userMap[(int) $wpUid] ?? null) && array_key_exists($emoji, Reaction::EMOJIS)) {
                                Reaction::firstOrCreate(['user_id' => $uid, 'reactable_type' => 'message', 'reactable_id' => $msg->id, 'emoji' => $emoji]);
                            }
                        }
                    }
                }
            }
            if ($last = Message::where('conversation_id', $conv->id)->max('created_at')) {
                $conv->forceFill(['last_message_at' => $last])->save();
            }
            // Lesestand
            foreach ($seen as $wpUid => $metas) {
                $ts = (int) ($metas[$this->config['meta']['chat_seen'].$id] ?? 0);
                if ($ts && ($uid = $this->userMap[(int) $wpUid] ?? null)) {
                    ConversationParticipant::updateOrCreate(['conversation_id' => $conv->id, 'user_id' => $uid], ['last_read_at' => Carbon::createFromTimestampUTC($ts)]);
                }
            }
        }
    }

    /* ---------- Wochen der Hybrid-Kurse aus den Gruppencalls ---------- */

    protected function importWeeks(): void
    {
        if ($this->dryRun) {
            return;
        }
        $tz = $this->tenant->timezone ?: config('app.timezone');

        foreach ($this->programMap as $program) {
            if ($program->pacing !== 'weekly') {
                continue;
            }
            $calls = Event::where('program_id', $program->id)->where('type', 'group_call')->whereNull('user_id')->orderBy('starts_at')->get();
            $steps = ProgramStep::where('program_id', $program->id)->get();
            $nr = 0;
            foreach ($calls as $call) {
                $nr++;
                $step = $call->step_id ? $steps->firstWhere('id', $call->step_id) : null;
                if (! $step) {
                    $clean = mb_strtolower($this->cleanCallTitle($call->title));
                    $step = $steps->first(fn (ProgramStep $s) => mb_strtolower($this->cleanCallTitle($s->title)) === $clean);
                }
                if (! $step) {
                    continue;
                }
                $montag = $call->starts_at->copy()->setTimezone($tz)->startOfWeek();
                $step->forceFill(['week_number' => $nr, 'unlocks_at' => $montag->utc()])->save();
                if (! $call->step_id) {
                    $call->timestamps = false;   // Zeitstempel aus WordPress behalten
                    $call->forceFill(['step_id' => $step->id])->saveQuietly();
                    $call->timestamps = true;
                }
                $this->stats['wochen']++;
            }
        }
    }

    /** 'Gruppencall Schritt 2, Teil 1: Ressourcen und Tools' -> 'ressourcen und tools' */
    protected function cleanCallTitle(string $t): string
    {
        $t = trim(html_entity_decode($t, ENT_QUOTES, 'UTF-8'));
        $t = preg_replace('~^Gruppencall\s*:?\s*~iu', '', $t);
        if (preg_match('~^Schritt\s*\d+[^:]*:\s*(.+)$~u', $t, $m)) {
            $t = trim($m[1]);
        }

        return $t;
    }

    /* ---------- Helfer ---------- */

    /** WordPress speichert post_date und comment_date in Ortszeit: nach UTC umrechnen. */
    protected function lokal(?string $wann): ?Carbon
    {
        if (! $wann || str_starts_with($wann, '0000')) {
            return null;
        }

        return Carbon::parse($wann, $this->tenant->timezone ?: config('app.timezone'))->utc();
    }

    /** Angelegt/geaendert wie in WordPress (lokale Zeit), damit Importiertes nicht als "neu" gilt. */
    protected function keepWpTimes($model, object $post): void
    {
        $tz = $this->tenant->timezone ?: config('app.timezone');
        $created = $post->post_date ? Carbon::parse($post->post_date, $tz)->utc() : null;
        $updated = $post->post_modified ? Carbon::parse($post->post_modified, $tz)->utc() : $created;
        if (! $created) {
            return;
        }
        $model->timestamps = false;
        $model->forceFill(['created_at' => $created, 'updated_at' => $updated ?? $created])->saveQuietly();
        $model->timestamps = true;
    }

    protected function visibility(string $sicht): string
    {
        return match ($sicht) {
            'lea' => 'coach', 'kurs' => 'program', 'alle' => 'all', default => 'private'
        };
    }

    protected function coachWpIds(): array
    {
        $ids = array_map('intval', (array) $this->config['owner_ids']);
        foreach ($this->source->users() as $u) {
            $roles = $this->source->rolesFromMeta($this->source->userMeta((int) $u->ID));
            if (array_intersect($roles, (array) $this->config['team_roles']) !== []) {
                $ids[] = (int) $u->ID;
            }
        }

        return array_values(array_unique($ids));
    }

    /** WordPress-Zeitstempel (lokale Wanduhr) in UTC. */
    protected function ts(int $ts): Carbon
    {
        if (! $this->config['event_timestamps_are_local']) {
            return Carbon::createFromTimestampUTC($ts);
        }
        $tz = $this->tenant->timezone ?: config('app.timezone');

        return Carbon::createFromFormat('Y-m-d H:i:s', gmdate('Y-m-d H:i:s', $ts), $tz)->utc();
    }

    /** Datei aus wp-content/uploads nach storage kopieren, wenn der Ordner erreichbar ist. */
    protected function copyUpload(?string $url, string $ziel): ?string
    {
        $dir = $this->config['uploads_dir'];
        if (! $url || ! $dir || ! is_dir($dir)) {
            return null;
        }
        if (! preg_match('~/wp-content/uploads/(.+)$~', $url, $m)) {
            return null;
        }
        $rel = urldecode($m[1]);
        $src = rtrim($dir, '/').'/'.$rel;
        if (! is_file($src)) {
            return null;
        }
        $path = "tenants/{$this->tenant->id}/{$ziel}/".Str::slug(pathinfo($rel, PATHINFO_FILENAME)).'-'.substr(md5($rel), 0, 6).'.'.strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (! Storage::exists($path)) {
            Storage::put($path, file_get_contents($src));
        }

        return $path;
    }

    protected function copyAttachment(int $attachmentId, string $ziel): ?string
    {
        $file = (string) $this->source->meta($attachmentId, '_wp_attached_file');
        if ($file === '') {
            return null;
        }
        $base = $this->config['uploads_url'] ?: 'https://example.invalid/wp-content/uploads';

        return $this->copyUpload(rtrim($base, '/').'/'.$file, $ziel);
    }
}
