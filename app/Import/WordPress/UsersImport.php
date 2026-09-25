<?php

namespace App\Import\WordPress;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Import 1: Personen und Rollen aus WordPress in users + memberships.
 * Wiederholbar: Mitgliedschaft wird ueber tenant_id + legacy_id (WordPress-ID) gefunden,
 * die Person ueber die Mailadresse.
 *
 * Zuordnung (aus tenants.settings.import.wordpress):
 * - owner_ids            WordPress-IDs der Coachin           -> owner
 * - team_roles           WordPress-Rollen fuer Assistenz     -> team
 * - Zugang zu einem Kurs (Meta "access" oder Relation)      -> member
 * - alles andere                                            -> guest (nur mit --with-guests)
 */
class UsersImport
{
    public array $stats = ['gelesen' => 0, 'angelegt' => 0, 'aktualisiert' => 0, 'uebersprungen' => 0, 'rollen' => []];

    protected array $config;

    /** WordPress-IDs mit eigener 1:1-Begleitung (Termin oder Einzelkurs), einmal gelesen */
    protected ?array $oneOnOneIds = null;

    public function __construct(
        protected Tenant $tenant,
        protected WordPressSource $source,
        protected bool $withGuests = false,
        protected bool $dryRun = false,
    ) {
        $this->config = array_replace_recursive([
            'owner_ids' => [],
            'team_roles' => ['administrator'],
            'course_relation_id' => null,
            'one_on_one_meta' => ['nvc_person', 'einzel_person'],   // postmeta, das auf die Person zeigt
            'meta' => [
                'phone' => null,
                'reminders_off' => null,
                'evening_mail_off' => null,
                'task_reminders_off' => null,
                'onboarding_seen' => null,
                'access' => null,
            ],
        ], (array) $tenant->setting('import.wordpress', []));
    }

    public function run(?callable $report = null): array
    {
        foreach ($this->source->users() as $wpUser) {
            $this->stats['gelesen']++;

            $email = Str::lower(trim((string) $wpUser->user_email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->stats['uebersprungen']++;
                $report && $report("#{$wpUser->ID} ohne gueltige Mailadresse, uebersprungen");

                continue;
            }

            $meta = $this->source->userMeta((int) $wpUser->ID);
            $role = $this->roleFor((int) $wpUser->ID, $meta);

            if ($role === Role::Guest && ! $this->withGuests) {
                $this->stats['uebersprungen']++;

                continue;
            }

            $this->stats['rollen'][$role->value] = ($this->stats['rollen'][$role->value] ?? 0) + 1;

            if ($this->dryRun) {
                $report && $report("#{$wpUser->ID} {$email} -> {$role->value}");

                continue;
            }

            $user = $this->upsertUser($wpUser, $meta, $email);
            $membership = $this->upsertMembership($user, $wpUser, $meta, $role);

            $this->stats[$membership->wasRecentlyCreated ? 'angelegt' : 'aktualisiert']++;
            $report && $report("#{$wpUser->ID} {$email} -> {$role->value}".($membership->wasRecentlyCreated ? ' (neu)' : ''));
        }

        return $this->stats;
    }

    public function roleFor(int $wpId, array $meta): Role
    {
        if (in_array($wpId, array_map('intval', (array) $this->config['owner_ids']), true)) {
            return Role::Owner;
        }

        $wpRoles = $this->source->rolesFromMeta($meta);
        if (array_intersect($wpRoles, (array) $this->config['team_roles']) !== []) {
            return Role::Team;
        }

        if ($this->hasCourseAccess($wpId, $meta)) {
            return Role::Member;
        }

        // Eigene 1:1-Termine oder ein Einzelkurs, aber kein Kurszugang: 1:1-Klientin
        return in_array($wpId, $this->oneOnOneIds(), true) ? Role::Client : Role::Guest;
    }

    protected function oneOnOneIds(): array
    {
        if ($this->oneOnOneIds !== null) {
            return $this->oneOnOneIds;
        }
        $keys = (array) ($this->config['one_on_one_meta'] ?? []);
        if ($keys === [] || ! $this->source->hasTable('postmeta') || ! $this->source->hasTable('posts')) {
            return $this->oneOnOneIds = [];
        }

        return $this->oneOnOneIds = $this->source->db()->table('postmeta as m')
            ->join('posts as p', 'p.ID', '=', 'm.post_id')
            ->whereIn('m.meta_key', $keys)
            ->whereIn('p.post_status', ['publish', 'private', 'draft', 'future'])
            ->pluck('m.meta_value')
            ->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
    }

    protected function hasCourseAccess(int $wpId, array $meta): bool
    {
        if ($key = $this->config['meta']['access'] ?? null) {
            $access = WordPressSource::unserialize($meta[$key] ?? null);
            if (is_array($access)) {
                foreach ($access as $entry) {
                    $bis = (int) ($entry['bis'] ?? 0);
                    if ($bis === 0 || $bis > time()) {
                        return true;
                    }
                }
            }
        }

        if ($rel = $this->config['course_relation_id'] ?? null) {
            return $this->source->relatedCourseIds($wpId, (int) $rel) !== [];
        }

        return false;
    }

    protected function upsertUser(object $wpUser, array $meta, string $email): User
    {
        $name = trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));
        $name = $name !== '' ? $name : trim((string) $wpUser->display_name);
        $name = $name !== '' ? $name : Str::before($email, '@');

        $phoneKey = $this->config['meta']['phone'] ?? null;
        $phone = $phoneKey ? trim((string) ($meta[$phoneKey] ?? '')) : '';

        $user = User::firstOrNew(['email' => $email]);

        // Was die Person in der App schon selbst gepflegt hat, bleibt stehen.
        if (! $user->exists || blank($user->name)) {
            $user->name = $name;
        }
        if ($phone !== '' && blank($user->phone)) {
            $user->phone = $phone;
        }
        if (! $user->exists) {
            $user->email_verified_at = now();
        }
        $user->save();

        return $user;
    }

    protected function upsertMembership(User $user, object $wpUser, array $meta, Role $role): Membership
    {
        $m = $this->config['meta'];
        $settings = [
            'notifications' => [
                'termine' => ! $this->flag($meta, $m['reminders_off'] ?? null),
                'abendmail' => ! $this->flag($meta, $m['evening_mail_off'] ?? null),
                'aufgaben' => ! $this->flag($meta, $m['task_reminders_off'] ?? null),
            ],
        ];
        if (($key = $m['onboarding_seen'] ?? null) && ! empty($meta[$key])) {
            $seen = (int) $meta[$key];
            $settings['onboarding_seen_at'] = $seen > 1 ? date('c', $seen) : now()->toIso8601String();
        }

        $membership = Membership::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where(fn ($q) => $q->where('legacy_id', (string) $wpUser->ID)->orWhere('user_id', $user->id))
            ->first() ?? new Membership(['tenant_id' => $this->tenant->id]);

        $membership->fill([
            'user_id' => $user->id,
            'legacy_id' => (string) $wpUser->ID,
            'role' => $role,
            'status' => $membership->exists ? $membership->status : 'active',
            'joined_at' => $membership->joined_at ?? ($wpUser->user_registered ?: now()),
            'settings' => array_replace_recursive($membership->settings ?? [], $settings),
        ])->save();

        return $membership;
    }

    protected function flag(array $meta, ?string $key): bool
    {
        return $key !== null && ! empty($meta[$key]);
    }
}
