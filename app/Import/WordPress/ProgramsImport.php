<?php

namespace App\Import\WordPress;

use App\Models\Answer;
use App\Models\Entitlement;
use App\Models\Exercise;
use App\Models\Membership;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Progress;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import 2: Kurse, Module, Lektionen, Arbeitsbuecher, Fortschritt, Antworten und Zugaenge.
 *
 * WordPress                                  -> App
 * CPT kurs                                   -> programs
 * CPT modul (Relation 9)                     -> program_steps
 * CPT lektion (Relation 10)                  -> units (lesson) + exercises (checkbox aus todos)
 * Arbeitsbuch-JSON (arbeitsbuch am Kurs)     -> program_steps + units (exercise_set) + exercises
 * usermeta je_data_store_erledigt            -> progress
 * usermeta lea_todos_{lektion}               -> answers (checkbox)
 * usermeta lea_wb_antworten[_{buch}]         -> answers
 * usermeta lea_wb_geteilt[_{buch}]           -> answers.shared_with_coach / program_members.share_mode
 * lea_zugaenge (kauf, abo), Relation 13,
 * lea_kurse_manuell, einzel_person           -> offers, offer_products, entitlements, program_members
 *
 * Wiederholbar ueber legacy_id (WordPress-ID) bzw. legacy_key.
 */
class ProgramsImport
{
    public array $stats = ['programme' => 0, 'schritte' => 0, 'einheiten' => 0, 'uebungsteile' => 0, 'fortschritt' => 0, 'antworten' => 0, 'zugaenge' => 0, 'mitglieder' => 0, 'hinweise' => []];

    protected array $config;

    /** WordPress-User-ID => users.id (ueber memberships.legacy_id) */
    protected array $userMap = [];

    /** WordPress-Kurs-ID => Program */
    protected array $programs = [];

    /** WordPress-Lektion-ID => Unit */
    protected array $units = [];

    public function __construct(
        protected Tenant $tenant,
        protected WordPressSource $source,
        protected bool $dryRun = false,
    ) {
        $this->config = array_replace_recursive([
            'course_relation_id' => 13,
            'relations' => ['course_modules' => 9, 'module_units' => 10],
            'club_product_id' => null,
            'program_types' => [],           // WordPress-Kurs-ID => hybrid | club | selfpaced | one_on_one
            'workbook_dir' => null,          // Ordner mit workbook-struktur.json und arbeitsbuch-*.json
            'meta' => ['access' => 'lea_zugaenge', 'progress' => 'je_data_store_erledigt', 'manual_courses' => 'lea_kurse_manuell',
                'unit_todos' => 'lea_todos_', 'workbook_answers' => 'lea_wb_antworten', 'workbook_shared' => 'lea_wb_geteilt'],
        ], (array) $this->tenant->setting('import.wordpress', []));
    }

    public function run(?callable $report = null): array
    {
        $this->report = $report;
        $this->userMap = Membership::query()->whereNotNull('legacy_id')->pluck('user_id', 'legacy_id')->mapWithKeys(fn ($u, $l) => [(int) $l => (int) $u])->all();

        $this->importPrograms();
        $this->importWorkbooks();
        $this->importProgress();
        $this->importAccess();

        return $this->stats;
    }

    protected $report = null;

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

    /* ---------- Kurse, Module, Lektionen ---------- */

    protected function importPrograms(): void
    {
        foreach ($this->source->posts('kurs') as $kurs) {
            $id = (int) $kurs->ID;
            $m = fn (string $k, $d = null) => $this->source->meta($id, $k, $d);

            $type = $this->config['program_types'][(string) $id] ?? $this->config['program_types'][$id] ?? null;
            if (! $type) {
                $type = match (true) {
                    $m('kurs_art') === 'einzel' => 'one_on_one',
                    default => 'selfpaced',
                };
            }
            $pacing = match ($type) {
                'hybrid' => 'weekly', 'one_on_one' => 'none', default => 'all'
            };

            $data = [
                'slug' => $this->uniqueSlug($kurs->post_name ?: Str::slug($kurs->post_title), $id),
                'title' => html_entity_decode($kurs->post_title, ENT_QUOTES, 'UTF-8'),
                'subtitle' => $m('untertitel') ?: null,
                'description' => WordPressSource::autop($kurs->post_content),
                'type' => $type,
                'pacing' => $pacing,
                'starts_at' => ($ts = (int) $m('start_datum')) ? date('Y-m-d', $ts) : null,
                'ends_at' => ($ts = (int) $m('laufzeit_bis')) ? date('Y-m-d', $ts) : null,
                'color' => $m('kursfarbe') ?: null,
                'icon' => ($i = (string) $m('kursicon')) ? preg_replace('~^fa-~', '', $i) : null,
                'cover_url' => $m('cover_url') ?: null,
                'is_published' => $kurs->post_status === 'publish',
                'is_internal' => (bool) ($m('nur_intern') || $m('lea_nur_intern')),
                'position' => (int) $m('reihenfolge', 0),
                'settings' => array_filter([
                    'kategorie' => $m('kurs_kategorie'),
                    'sitzungen_gesamt' => $m('sitzungen_gesamt') ? (int) $m('sitzungen_gesamt') : null,
                    'im_club' => (bool) $m('im_club'),
                    'arbeitsbuch' => $m('arbeitsbuch') ?: null,
                    'zugang_tage' => $m('zugang_tage') !== null && $m('zugang_tage') !== '' ? (int) $m('zugang_tage') : null,
                    'produkte' => $this->productIds($id),
                    'einzel_person' => $m('einzel_person') ? (int) $m('einzel_person') : null,
                    'gratis' => (bool) $m('kurs_gratis') ?: null,
                ], fn ($v) => $v !== null && $v !== false && $v !== []),
            ];

            $program = Program::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('legacy_id', (string) $id)->first();
            $this->say("Kurs #{$id} {$data['title']} -> {$type}".($program ? '' : ' (neu)'));
            $this->stats['programme']++;

            if ($this->dryRun) {
                continue;
            }

            $program ??= new Program(['tenant_id' => $this->tenant->id, 'legacy_id' => (string) $id]);
            // Slug nur beim ersten Mal setzen, damit Links stabil bleiben
            if ($program->exists) {
                unset($data['slug']);
            }
            $program->fill($data)->save();
            $this->programs[$id] = $program;

            $this->importSteps($program, $id);
        }
    }

    protected function importSteps(Program $program, int $kursId): void
    {
        $order = 0;
        foreach ($this->source->children($this->config['relations']['course_modules'], $kursId) as $modulId) {
            $modul = $this->source->post($modulId);
            if (! $modul || $modul->post_status === 'trash') {
                continue;
            }
            $order++;
            $step = ProgramStep::firstOrNew(['program_id' => $program->id, 'legacy_id' => (string) $modulId]);
            $step->fill([
                'title' => html_entity_decode($modul->post_title, ENT_QUOTES, 'UTF-8'),
                'position' => (int) ($this->source->meta($modulId, 'reihenfolge') ?: $order),
                'summary' => WordPressSource::autop($modul->post_content),
                'settings' => array_filter([
                    'schritt_nr' => (int) $this->source->meta($modulId, 'schritt_nr') ?: null,
                    'teil_nr' => (int) $this->source->meta($modulId, 'teil_nr') ?: null,
                ]),
            ])->save();
            $this->stats['schritte']++;

            $uorder = 0;
            foreach ($this->source->children($this->config['relations']['module_units'], $modulId) as $lektionId) {
                $lektion = $this->source->post($lektionId);
                if (! $lektion || $lektion->post_status === 'trash') {
                    continue;
                }
                $uorder++;
                $this->importUnit($program, $step, $lektion, $uorder);
            }
        }
    }

    protected function importUnit(Program $program, ProgramStep $step, object $lektion, int $order): void
    {
        $id = (int) $lektion->ID;
        $videos = collect((array) $this->source->meta($id, 'videos', []))
            ->filter(fn ($v) => is_array($v) && filled($v['video_url'] ?? null))
            ->map(fn ($v) => ['url' => trim($v['video_url']), 'title' => trim((string) ($v['video_titel'] ?? ''))])
            ->values()->all();
        $links = collect((array) $this->source->meta($id, 'links', []))
            ->filter(fn ($l) => is_array($l) && filled($l['link_url'] ?? null))
            ->map(fn ($l) => ['url' => trim($l['link_url']), 'title' => trim((string) ($l['link_text'] ?? ''))])
            ->values()->all();

        $unit = Unit::firstOrNew(['program_id' => $program->id, 'legacy_id' => (string) $id]);
        $unit->fill([
            'step_id' => $step->id,
            'title' => html_entity_decode($lektion->post_title, ENT_QUOTES, 'UTF-8'),
            'type' => 'lesson',
            'position' => (int) ($this->source->meta($id, 'reihenfolge') ?: $order),
            'intro' => trim(strip_tags((string) $this->source->meta($id, 'info', ''), '<br>')) ?: null,
            'body' => WordPressSource::autop($lektion->post_content),
            'videos' => $videos ?: null,
            'links' => $links ?: null,
            'is_published' => $lektion->post_status === 'publish',
        ])->save();
        $this->units[$id] = $unit;
        $this->stats['einheiten']++;

        // Aufgaben zum Abhaken aus <li>
        $todos = (string) $this->source->meta($id, 'todos', '');
        $items = [];
        if (preg_match_all('~<li[^>]*>(.*?)</li>~is', $todos, $m)) {
            $items = array_values(array_filter(array_map('trim', $m[1])));
        } elseif (trim(strip_tags($todos)) !== '') {
            $items = [trim(strip_tags($todos))];
        }
        foreach ($items as $i => $text) {
            $ex = Exercise::firstOrNew(['unit_id' => $unit->id, 'legacy_key' => 'todo-'.$id.'-'.$i]);
            $ex->fill(['type' => 'checkbox', 'position' => $i + 1, 'prompt' => trim(strip_tags($text))])->save();
            $this->stats['uebungsteile']++;
        }
    }

    /* ---------- Arbeitsbuecher (JSON) ---------- */

    protected function importWorkbooks(): void
    {
        $dir = $this->config['workbook_dir'];
        if (! $dir || ! is_dir($dir)) {
            $this->hint('Arbeitsbuch-Ordner nicht erreichbar, Arbeitsbuecher uebersprungen: '.($dir ?: '(nicht gesetzt)'));

            return;
        }

        $books = [];
        if (is_file($dir.'/workbook-struktur.json')) {
            $books['workbook'] = ['file' => $dir.'/workbook-struktur.json', 'title' => 'Von der Idee zur zahlenden Kundin', 'lead' => null, 'meta' => $this->config['meta']['workbook_answers'], 'shared' => $this->config['meta']['workbook_shared']];
        }
        foreach ((array) glob($dir.'/arbeitsbuch-*.json') as $file) {
            $key = preg_replace('~^arbeitsbuch-|\.json$~', '', basename($file));
            $books[$key] = ['file' => $file, 'title' => $key, 'lead' => null, 'meta' => $this->config['meta']['workbook_answers'].'_'.$key, 'shared' => $this->config['meta']['workbook_shared'].'_'.$key];
        }

        foreach ($books as $key => $book) {
            $raw = json_decode((string) file_get_contents($book['file']), true);
            if (! is_array($raw)) {
                $this->hint("Arbeitsbuch {$key}: JSON nicht lesbar");

                continue;
            }
            $kopf = $raw['buch'] ?? [];
            $steps = $raw['schritte'] ?? $raw;
            $title = $kopf['titel'] ?? $book['title'];

            // Zu welchem Programm gehoert das Buch? Am Kurs steht arbeitsbuch = key, sonst eigenes Programm.
            $program = collect($this->programs)->first(fn (Program $p) => ($p->settings['arbeitsbuch'] ?? null) === $key);
            if (! $program) {
                $program = Program::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('legacy_id', 'buch-'.$key)->first();
                $this->say("Arbeitsbuch {$key} -> eigenes Programm '{$title}'".($program ? '' : ' (neu)'));
                if ($this->dryRun) {
                    continue;
                }
                $program ??= new Program(['tenant_id' => $this->tenant->id, 'legacy_id' => 'buch-'.$key, 'slug' => $this->uniqueSlug($key === 'workbook' ? 'workbook' : 'arbeitsbuch-'.$key, 'buch-'.$key)]);
                $program->fill(['title' => $title, 'subtitle' => $kopf['lead'] ?? null, 'type' => 'workbook', 'pacing' => 'all', 'is_published' => true])->save();
                $this->programs['buch-'.$key] = $program;
            } else {
                $this->say("Arbeitsbuch {$key} -> im Programm '{$program->title}'");
                if ($this->dryRun) {
                    continue;
                }
            }

            $this->stats['programme']++;
            $this->importBookSteps($program, $key, (array) $steps);
            $this->resolveReferences($program);
            $this->importBookAnswers($program, $key, $book);
        }
    }

    protected function importBookSteps(Program $program, string $key, array $steps): void
    {
        $offset = (int) ProgramStep::where('program_id', $program->id)
            ->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', 'not like', 'wb-%'))
            ->max('position');
        foreach ($steps as $i => $s) {
            if (! is_array($s) || empty($s['key'])) {
                continue;
            }
            $step = ProgramStep::firstOrNew(['program_id' => $program->id, 'legacy_id' => 'wb-'.$key.'-'.$s['key']]);
            $step->fill([
                'title' => $this->prettyTitle((string) ($s['titel'] ?? $s['key'])),
                'position' => $offset + $i + 1,
                'summary' => filled($s['lead'] ?? null) ? '<p>'.e($s['lead']).'</p>' : null,
                'settings' => array_filter(['art' => $s['art'] ?? null, 'nr' => $s['nr'] ?? null]),
            ])->save();
            $this->stats['schritte']++;

            foreach ((array) ($s['uebungen'] ?? []) as $j => $u) {
                if (! is_array($u) || empty($u['key'])) {
                    continue;
                }
                $unit = Unit::firstOrNew(['program_id' => $program->id, 'legacy_id' => 'wb-'.$key.'-'.$u['key']]);
                $unit->fill([
                    'step_id' => $step->id,
                    'title' => (string) ($u['titel'] ?? 'Übung '.($u['nr'] ?? $j + 1)),
                    'type' => 'exercise_set',
                    'position' => $j + 1,
                    'is_core' => ($u['badge'] ?? '') === 'KERN',
                    'settings' => array_filter(['badge' => $u['badge'] ?? null, 'nr' => $u['nr'] ?? null]),
                ])->save();
                $this->stats['einheiten']++;
                $this->importBookParts($unit, $u['key'], (array) ($u['teile'] ?? []));
            }

            // Teile direkt am Schritt (Notizen, Quellen) haengen an einer eigenen Einheit
            if (! empty($s['teile'])) {
                $unit = Unit::firstOrNew(['program_id' => $program->id, 'legacy_id' => 'wb-'.$key.'-'.$s['key'].'-teile']);
                $unit->fill(['step_id' => $step->id, 'title' => $this->prettyTitle((string) ($s['titel'] ?? '')), 'type' => 'exercise_set', 'position' => 0])->save();
                $this->stats['einheiten']++;
                $this->importBookParts($unit, $s['key'], (array) $s['teile']);
            }
        }
    }

    protected function importBookParts(Unit $unit, string $ukey, array $parts): void
    {
        $lastSub = '';
        foreach ($parts as $idx => $teil) {
            $i = $idx + 1;
            $t = $teil['t'] ?? '';
            $text = (string) ($teil['text'] ?? '');
            [$type, $options] = match ($t) {
                'frage', 'feld' => ['text', null],
                'notizfeld' => ['note', null],
                'skala' => ['scale', null],
                'werte' => ['values', ['values' => collect((array) ($teil['zeilen'] ?? []))->flatten()->map(fn ($v) => (string) $v)->values()->all()]],
                'sub' => ['heading', null],
                'lead', 'text' => ['hint', null],
                'liste' => ['list', array_filter(['platzhalter' => $teil['platzhalter'] ?? null, 'mehr' => $teil['mehr'] ?? null])],
                'liste2' => ['pairs', array_filter(['links' => $teil['links'] ?? null, 'rechts' => $teil['rechts'] ?? null, 'platzhalter_links' => $teil['platzhalter_links'] ?? null, 'platzhalter_rechts' => $teil['platzhalter_rechts'] ?? null, 'mehr' => $teil['mehr'] ?? null])],
                'brieffeld' => ['letter', array_filter(['platzhalter' => $teil['platzhalter'] ?? null, 'zeilen' => isset($teil['zeilen']) ? (int) $teil['zeilen'] : null])],
                'spiegel' => ['mirror', array_filter(['quelle' => $teil['feld'] ?? null, 'leer' => $teil['leer'] ?? null])],
                'aufnahme' => ['audio', array_filter(['quelle' => $teil['spiegel'] ?? null])],
                'mitnehmen' => ['takeaway', null],
                'praxis' => ['practice', ['tage' => (int) ($teil['tage'] ?? 21), 'aufgabe' => $teil['aufgabe'] ?? 'Deinen Brief laut lesen']],
                'grafik' => ($teil['bild'] ?? '') === 'lebensrad' ? ['wheel', null] : [null, null],
                default => [null, null],
            };
            if ($t === 'sub') {
                $lastSub = $text;
            }
            if ($type === null) {
                continue;
            }
            $ex = Exercise::firstOrNew(['unit_id' => $unit->id, 'legacy_key' => $ukey.'-f'.$i]);
            // Verweise auf andere Felder (Spiegel, Aufnahme) bleiben erhalten, die Aufloesung macht resolveReferences
            $options = $options ? array_merge($ex->options ?? [], $options) : null;
            $ex->fill([
                'type' => $type,
                'position' => $i,
                'title' => $t === 'sub' ? $text : ($type === 'scale' && $lastSub ? $lastSub : null),
                'prompt' => $t === 'sub' ? null : ($text ?: ($type === 'note' ? 'Notizen' : null)),
                'options' => $options ?: null,
            ])->save();
            $this->stats['uebungsteile']++;
        }
    }

    /** Spiegel und Aufnahme zeigen die Antwort eines anderen Feldes: Feldschluessel -> exercise_id. */
    protected function resolveReferences(Program $program): void
    {
        $exercises = Exercise::whereIn('unit_id', $program->units()->pluck('id'))->get();
        $byKey = $exercises->whereNotNull('legacy_key')->pluck('id', 'legacy_key');
        foreach ($exercises->whereIn('type', ['mirror', 'audio']) as $ex) {
            $quelle = $ex->options['quelle'] ?? null;
            if ($quelle && isset($byKey[$quelle])) {
                $ex->options = array_merge($ex->options ?? [], ['exercise_id' => $byKey[$quelle]]);
                $ex->save();
            }
        }
    }

    /** Antwortwert aus WordPress in die Form des Uebungsteils bringen. */
    protected function convertAnswer(string $type, mixed $value, int $userId): mixed
    {
        $zeilen = fn ($v) => array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $v))), fn ($z) => $z !== ''));

        return match ($type) {
            'list' => is_array($value) ? array_values($value) : $zeilen($value),
            'pairs' => is_array($value) ? array_values($value) : array_map(fn ($z) => array_pad(array_map('trim', explode(' :: ', $z, 2)), 2, ''), $zeilen($value)),
            'scale' => is_numeric($value) ? (int) $value : $value,
            'audio' => $this->copyRecording((string) $value, $userId),
            'values', 'choice' => is_string($value) && str_contains($value, '|') ? array_values(array_filter(array_map('trim', explode('|', $value)))) : $value,
            default => $value,
        };
    }

    /** Aufnahme aus den WordPress-Uploads in den privaten Speicher des Mandanten kopieren. */
    protected function copyRecording(string $url, int $userId): ?string
    {
        $base = rtrim((string) ($this->config['uploads_url'] ?? ''), '/');
        $dir = rtrim((string) ($this->config['uploads_dir'] ?? ''), '/');
        if ($url === '' || $base === '' || ! str_starts_with($url, $base.'/')) {
            return null;
        }
        $quelle = $dir.substr($url, strlen($base));
        if (! is_file($quelle)) {
            return null;
        }
        $ziel = 'tenants/'.$this->tenant->id.'/answers/'.$userId.'/'.basename($quelle);
        if (! Storage::exists($ziel)) {
            Storage::put($ziel, file_get_contents($quelle));
        }

        return $ziel;
    }

    protected function importBookAnswers(Program $program, string $key, array $book): void
    {
        $exercises = Exercise::whereIn('unit_id', $program->units()->pluck('id'))->whereNotNull('legacy_key')->pluck('id', 'legacy_key');
        $unitOfExercise = Exercise::whereIn('unit_id', $program->units()->pluck('id'))->pluck('unit_id', 'id');
        $types = Exercise::whereIn('unit_id', $program->units()->pluck('id'))->pluck('type', 'id');
        $unitByKey = $program->units()->whereNotNull('legacy_id')->get()->mapWithKeys(fn (Unit $u) => [str_replace('wb-'.$key.'-', '', $u->legacy_id) => $u->id]);

        $shared = $this->source->userMetaByKey($book['shared']);
        // "Darf Lea mitlesen?" einmal am Anfang (lea_wb_freigabe) und selbst gesetzte Erledigt-Haken je Uebung
        $freigabe = $this->source->userMetaByKey($this->config['meta']['workbook_release'] ?? 'lea_wb_freigabe');
        foreach ($this->source->userMetaByKey($book['meta'].'_erledigt') as $wpUid => $raw) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            $keys = WordPressSource::unserialize($raw);
            if (! $uid || ! is_array($keys) || $this->dryRun) {
                continue;
            }
            foreach ($keys as $k) {
                if (($unitId = $unitByKey[(string) $k] ?? null) && ! Progress::where('user_id', $uid)->where('unit_id', $unitId)->whereNotNull('completed_at')->exists()) {
                    Progress::updateOrCreate(['user_id' => $uid, 'unit_id' => $unitId], ['completed_at' => now()]);
                    $this->stats['fortschritt']++;
                }
            }
        }

        foreach ($this->source->userMetaByKey($book['meta']) as $wpUid => $raw) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            if (! $uid) {
                continue;
            }
            $answers = WordPressSource::unserialize($raw);
            if (! is_array($answers)) {
                continue;
            }
            $sharedRaw = WordPressSource::unserialize($shared[$wpUid] ?? null);
            $shareAll = $sharedRaw === 'alles' || ($freigabe[$wpUid] ?? null) === 'alles';
            $sharedUnits = is_array($sharedRaw) ? array_keys($sharedRaw) : [];

            $member = ProgramMember::firstOrCreate(['program_id' => $program->id, 'user_id' => $uid], ['joined_at' => now()]);
            if ($shareAll && ! $member->share_mode) {
                $member->forceFill(['share_mode' => 'alles'])->save();
            } elseif (! $shareAll && $sharedRaw !== null && ! $member->share_mode) {
                $member->forceFill(['share_mode' => 'einzeln'])->save();
            }
            $this->stats['mitglieder']++;

            foreach ($answers as $fieldKey => $value) {
                $exId = $exercises[$fieldKey] ?? null;
                if (! $exId) {
                    continue;
                }
                $unitKey = preg_replace('~-f\d+$~', '', $fieldKey);
                $isShared = $shareAll || in_array($unitKey, $sharedUnits, true);
                $v = $this->convertAnswer($types[$exId] ?? 'text', $value, $uid);
                if ($v === null) {
                    continue;
                }
                $answer = Answer::firstOrNew(['user_id' => $uid, 'exercise_id' => $exId]);
                if (! $answer->exists) {
                    $answer->value = ['v' => $v];
                }
                $answer->shared_with_coach = $answer->shared_with_coach || $isShared;
                $answer->shared_at ??= $isShared ? now() : null;
                $answer->save();
                $this->stats['antworten']++;
            }
        }
    }

    /* ---------- Fortschritt ---------- */

    protected function importProgress(): void
    {
        if ($this->dryRun || $this->units === []) {
            return;
        }

        foreach ($this->source->userMetaByKey($this->config['meta']['progress']) as $wpUid => $raw) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            $ids = WordPressSource::unserialize($raw);
            if (! $uid || ! is_array($ids)) {
                continue;
            }
            foreach ($ids as $lektionId) {
                $unit = $this->units[(int) $lektionId] ?? null;
                if (! $unit) {
                    continue;
                }
                $row = Progress::firstOrNew(['user_id' => $uid, 'unit_id' => $unit->id]);
                if (! $row->completed_at) {
                    $row->completed_at = now();
                    $row->save();
                    $this->stats['fortschritt']++;
                }
            }
        }

        // Abgehakte Aufgaben je Lektion: lea_todos_{lektion} = [index, ...]
        foreach ($this->source->userMetaLike($this->config['meta']['unit_todos']) as $wpUid => $metas) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            if (! $uid) {
                continue;
            }
            foreach ($metas as $metaKey => $raw) {
                $lektionId = (int) substr($metaKey, strlen($this->config['meta']['unit_todos']));
                $unit = $this->units[$lektionId] ?? null;
                $indexes = WordPressSource::unserialize($raw);
                if (! $unit || ! is_array($indexes)) {
                    continue;
                }
                foreach ($indexes as $i) {
                    $ex = Exercise::where('unit_id', $unit->id)->where('legacy_key', 'todo-'.$lektionId.'-'.(int) $i)->first();
                    if ($ex) {
                        Answer::firstOrCreate(['user_id' => $uid, 'exercise_id' => $ex->id], ['value' => ['v' => true]]);
                        $this->stats['antworten']++;
                    }
                }
            }
        }
    }

    /* ---------- Zugaenge ---------- */

    protected function importAccess(): void
    {
        if ($this->dryRun || $this->programs === []) {
            return;
        }

        $clubProduct = $this->config['club_product_id'] ? (string) $this->config['club_product_id'] : null;

        // Ein Angebot je Kurs mit Produkt, dazu ein Club-Angebot fuer alle Clubkurse
        $offers = [];
        foreach ($this->programs as $legacy => $program) {
            if (! is_int($legacy)) {
                continue;
            }
            $products = array_diff($program->settings['produkte'] ?? [], array_filter([$clubProduct]));
            $offer = Offer::firstOrNew(['legacy_id' => 'kurs-'.$legacy]);
            $offer->fill([
                'title' => 'Zugang: '.$program->title,
                'type' => match ($program->type) {
                    'hybrid' => 'hybrid', 'one_on_one' => 'one_on_one', default => (($program->settings['kategorie'] ?? '') === 'gratis' ? 'free' : 'course')
                },
                'is_free' => ($program->settings['kategorie'] ?? '') === 'gratis',
                'access_days' => array_key_exists('zugang_tage', $program->settings ?? []) ? ($program->settings['zugang_tage'] ?: null) : 365,
            ])->save();
            $offer->programs()->syncWithoutDetaching([$program->id => ['tenant_id' => $this->tenant->id]]);
            foreach ($products as $pid) {
                OfferProduct::firstOrCreate(['source' => 'woocommerce', 'external_id' => (string) $pid], ['offer_id' => $offer->id]);
            }
            $offers[$legacy] = $offer;
        }

        $club = null;
        if ($clubProduct) {
            $club = Offer::firstOrNew(['legacy_id' => 'club']);
            $club->fill(['title' => 'Club', 'type' => 'club', 'access_days' => null])->save();
            OfferProduct::firstOrCreate(['source' => 'woocommerce', 'external_id' => $clubProduct], ['offer_id' => $club->id]);
            foreach ($this->programs as $legacy => $program) {
                if (is_int($legacy) && ! empty($program->settings['im_club'])) {
                    $club->programs()->syncWithoutDetaching([$program->id => ['tenant_id' => $this->tenant->id]]);
                }
            }
        }

        // lea_zugaenge: kauf/abo -> entitlement, hand -> direktes Mitglied
        foreach ($this->source->userMetaByKey($this->config['meta']['access']) as $wpUid => $raw) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            $entries = WordPressSource::unserialize($raw);
            if (! $uid || ! is_array($entries)) {
                continue;
            }
            foreach ($entries as $kursId => $e) {
                $program = $this->programs[(int) $kursId] ?? null;
                if (! $program || ! is_array($e)) {
                    continue;
                }
                $quelle = (string) ($e['quelle'] ?? 'hand');
                $bis = (int) ($e['bis'] ?? 0);
                $ab = (int) ($e['ab'] ?? 0);
                if ($quelle === 'hand') {
                    $this->member($uid, $program);

                    continue;
                }
                $offer = $quelle === 'abo' && $club ? $club : ($offers[(int) $kursId] ?? null);
                if (! $offer) {
                    continue;
                }
                $ref = $quelle === 'abo' ? 'abo' : (string) ($e['order'] ?? '');
                $ent = Entitlement::firstOrNew(['user_id' => $uid, 'offer_id' => $offer->id, 'source_ref' => $ref ?: null]);
                if (! $ent->exists) {
                    $ent->fill([
                        'source' => 'woocommerce',
                        'starts_at' => $ab ? date('Y-m-d H:i:s', $ab) : now(),
                        'ends_at' => $bis ? date('Y-m-d H:i:s', $bis) : null,
                        'status' => $bis && $bis < time() ? 'ended' : 'active',
                    ])->save();
                    $this->stats['zugaenge']++;
                }
            }
        }

        // Relation 13 und lea_kurse_manuell: direkte Mitglieder
        foreach ($this->source->relationPairs((int) $this->config['course_relation_id']) as [$wpUid, $kursId]) {
            if (($uid = $this->userMap[$wpUid] ?? null) && ($program = $this->programs[$kursId] ?? null)) {
                $this->member($uid, $program);
            }
        }
        foreach ($this->source->userMetaByKey($this->config['meta']['manual_courses']) as $wpUid => $raw) {
            $uid = $this->userMap[(int) $wpUid] ?? null;
            $ids = WordPressSource::unserialize($raw);
            if (! $uid || ! is_array($ids)) {
                continue;
            }
            foreach ($ids as $kursId) {
                if ($program = $this->programs[(int) $kursId] ?? null) {
                    $this->member($uid, $program);
                }
            }
        }

        // 1:1: die Person am Kurs
        foreach ($this->programs as $legacy => $program) {
            if (is_int($legacy) && ($wp = $program->settings['einzel_person'] ?? null) && ($uid = $this->userMap[(int) $wp] ?? null)) {
                $this->member($uid, $program);
            }
        }
    }

    protected function member(int $userId, Program $program): void
    {
        $m = ProgramMember::firstOrCreate(['program_id' => $program->id, 'user_id' => $userId], ['joined_at' => now()]);
        if ($m->wasRecentlyCreated) {
            $this->stats['mitglieder']++;
        }
    }

    /* ---------- Helfer ---------- */

    protected function productIds(int $kursId): array
    {
        $ids = [];
        if ($p = (int) $this->source->meta($kursId, 'produkt_id')) {
            $ids[] = (string) $p;
        }
        $liste = $this->source->meta($kursId, 'lea_produkte');
        foreach (is_array($liste) ? $liste : preg_split('~[^0-9]+~', (string) $liste, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            $ids[] = (string) (int) $p;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function uniqueSlug(string $slug, string|int $legacy): string
    {
        $slug = Str::slug($slug) ?: 'programm';
        $base = $slug;
        $n = 1;
        while (Program::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $slug)->where('legacy_id', '!=', (string) $legacy)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    /** WERTE & VORBILDER -> Werte und Vorbilder */
    protected function prettyTitle(string $t): string
    {
        if ($t !== '' && $t === mb_strtoupper($t, 'UTF-8')) {
            $t = mb_convert_case(mb_strtolower($t, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $t = str_replace([' Und ', ' & '], [' und ', ' und '], $t);
        }

        return $t;
    }
}
