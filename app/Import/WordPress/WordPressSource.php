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
    public function __construct(protected ?string $connection = 'wordpress') {}

    public function db(): Connection
    {
        return DB::connection($this->connection);
    }

    public function prefix(): string
    {
        return (string) $this->db()->getTablePrefix();
    }

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

    /** WordPress-Rollen aus {prefix}capabilities. */
    public function rolesFromMeta(array $meta): array
    {
        $caps = self::unserialize($meta[$this->prefix().'capabilities'] ?? null);

        return is_array($caps) ? array_keys(array_filter($caps)) : [];
    }

    /** Kurs-IDs aus der JetEngine-Relation (Teilnehmer zu Kurse). */
    public function relatedCourseIds(int $userId, int $relationId): array
    {
        if (! $this->db()->getSchemaBuilder()->hasTable('jet_rel_default')) {
            return [];
        }

        return $this->db()->table('jet_rel_default')
            ->where('rel_id', (string) $relationId)
            ->where('parent_object_id', $userId)
            ->pluck('child_object_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** PHP-serialisierte WordPress-Werte sicher lesen. */
    public static function unserialize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $result = @unserialize($value, ['allowed_classes' => false]);

        return $result === false && $value !== 'b:0;' ? $value : $result;
    }
}
