<?php

namespace App\Import\WordPress;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lesender Zugriff auf die WordPress-Datenbank (Verbindung "wordpress", nur lesen).
 * Der Tabellenpraefix kommt aus der Verbindung (WP_DB_PREFIX).
 */
class WordPressSource
{
    protected array $metaCache = [];

    public function __construct(protected ?string $connection = 'wordpress') {}

    public function db(): Connection
    {
        return DB::connection($this->connection);
    }

    public function prefix(): string
    {
        return (string) $this->db()->getTablePrefix();
    }

    public function hasTable(string $table): bool
    {
        return $this->db()->getSchemaBuilder()->hasTable($table);
    }

    /* ---------- Personen ---------- */

    /** Alle WordPress-Konten, aelteste zuerst. */
    public function users(): Collection
    {
        return $this->db()->table('users')
            ->select(['ID', 'user_login', 'user_email', 'display_name', 'user_registered'])
            ->orderBy('ID')
            ->get();
    }

    /** usermeta einer Person als [key => value]. */
    public function userMeta(int $userId): array
    {
        return $this->db()->table('usermeta')
            ->where('user_id', $userId)
            ->pluck('meta_value', 'meta_key')
            ->all();
    }

    /** Ein usermeta-Schluessel fuer alle Personen: [user_id => value]. */
    public function userMetaByKey(string $key): array
    {
        return $this->db()->table('usermeta')->where('meta_key', $key)->pluck('meta_value', 'user_id')->all();
    }

    /** Alle usermeta-Zeilen, deren Schluessel mit einem Praefix beginnt: [user_id => [key => value]]. */
    public function userMetaLike(string $prefix): array
    {
        $out = [];
        foreach ($this->db()->table('usermeta')->where('meta_key', 'like', $prefix.'%')->get() as $row) {
            if (str_starts_with($row->meta_key, $prefix)) {
                $out[(int) $row->user_id][$row->meta_key] = $row->meta_value;
            }
        }

        return $out;
    }

    /** WordPress-Rollen aus {prefix}capabilities. */
    public function rolesFromMeta(array $meta): array
    {
        $caps = self::unserialize($meta[$this->prefix().'capabilities'] ?? null);

        return is_array($caps) ? array_keys(array_filter($caps)) : [];
    }

    /* ---------- Inhalte ---------- */

    /** Beitraege eines Typs (ohne Papierkorb). */
    public function posts(string $type, array $statuses = ['publish', 'draft', 'private']): Collection
    {
        return $this->db()->table('posts')
            ->select(['ID', 'post_title', 'post_name', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_modified', 'post_author', 'menu_order'])
            ->where('post_type', $type)
            ->whereIn('post_status', $statuses)
            ->orderBy('menu_order')->orderBy('ID')
            ->get();
    }

    public function post(int $id): ?object
    {
        return $this->db()->table('posts')->where('ID', $id)->first();
    }

    /** postmeta eines Beitrags als [key => value] (serialisierte Werte bleiben roh). */
    public function postMeta(int $postId): array
    {
        return $this->metaCache[$postId] ??= $this->db()->table('postmeta')
            ->where('post_id', $postId)
            ->pluck('meta_value', 'meta_key')
            ->all();
    }

    public function meta(int $postId, string $key, mixed $default = null): mixed
    {
        $meta = $this->postMeta($postId);

        return array_key_exists($key, $meta) ? self::unserialize($meta[$key]) : $default;
    }

    /* ---------- JetEngine-Relationen ---------- */

    /** Kinder einer Relation: child_object_ids zu einem Elternteil. */
    public function children(int $relationId, int $parentId): array
    {
        if (! $this->hasTable('jet_rel_default')) {
            return [];
        }

        return $this->db()->table('jet_rel_default')
            ->where('rel_id', (string) $relationId)
            ->where('parent_object_id', $parentId)
            ->orderBy('_ID')
            ->pluck('child_object_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Eltern einer Relation: parent_object_ids zu einem Kind. */
    public function parents(int $relationId, int $childId): array
    {
        if (! $this->hasTable('jet_rel_default')) {
            return [];
        }

        return $this->db()->table('jet_rel_default')
            ->where('rel_id', (string) $relationId)
            ->where('child_object_id', $childId)
            ->pluck('parent_object_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Alle Paare einer Relation: [[parent, child], ...]. */
    public function relationPairs(int $relationId): array
    {
        if (! $this->hasTable('jet_rel_default')) {
            return [];
        }

        return $this->db()->table('jet_rel_default')
            ->where('rel_id', (string) $relationId)
            ->get()
            ->map(fn ($r) => [(int) $r->parent_object_id, (int) $r->child_object_id])
            ->all();
    }

    /** Kurs-IDs aus der JetEngine-Relation (Teilnehmer zu Kurse). */
    public function relatedCourseIds(int $userId, int $relationId): array
    {
        return $this->children($relationId, $userId);
    }

    /* ---------- Helfer ---------- */

    /** PHP-serialisierte WordPress-Werte sicher lesen. */
    public static function unserialize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $result = @unserialize($value, ['allowed_classes' => false]);

        return $result === false && $value !== 'b:0;' ? $value : $result;
    }

    /** Einfache Absaetze wie wpautop, wenn kein HTML-Block da ist. */
    public static function autop(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (preg_match('~<(p|div|ul|ol|h[1-6]|blockquote|table)\b~i', $text)) {
            return $text;
        }
        $parts = preg_split('~\n\s*\n~', str_replace(["\r\n", "\r"], "\n", $text));

        return implode('', array_map(fn ($p) => '<p>'.nl2br(trim($p)).'</p>', array_filter($parts, fn ($p) => trim($p) !== '')));
    }
}
