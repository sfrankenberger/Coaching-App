<?php

namespace App\Ai\Werkzeuge;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class ImpulsAnlegen extends Werkzeug
{
    public function name(): string
    {
        return 'impuls_anlegen';
    }

    public function beschreibung(): string
    {
        return 'Einen Impuls oder eine Neuigkeit in der App anlegen (Entwurf oder gleich veroeffentlicht). Beim Veroeffentlichen bekommen die Mitglieder auf Wunsch Push oder Mail.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'titel' => ['type' => 'string'],
            'text' => ['type' => 'string', 'description' => 'Absaetze mit Leerzeile, einfaches HTML erlaubt'],
            'kurz' => ['type' => 'string', 'description' => 'Kurz gesagt, fuer Liste und Benachrichtigung'],
            'art' => ['type' => 'string', 'enum' => ['impuls', 'neuigkeit']],
            'bild_url' => ['type' => 'string'],
            'veroeffentlichen' => ['type' => 'boolean', 'description' => 'Vorgabe: nein (Entwurf)'],
            'bescheid' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['push', 'mail']], 'description' => 'Kanaele beim Veroeffentlichen'],
        ], ['titel', 'text']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $titel = trim((string) ($args['titel'] ?? ''));
        abort_if($titel === '', 422, 'Kein Titel.');
        $text = trim((string) ($args['text'] ?? ''));
        $html = preg_match('~<(p|h[1-6]|ul|ol|br)\b~i', $text) ? $text : implode('', array_map(fn ($a) => '<p>'.nl2br(e(trim($a))).'</p>', preg_split('~\n\s*\n~', $text) ?: []));
        $basis = Str::slug($titel) ?: 'impuls';
        $slug = $basis;
        $n = 2;
        while (Post::where('slug', $slug)->exists()) {
            $slug = $basis.'-'.$n++;
        }
        $post = Post::create([
            'author_id' => $von->id, 'type' => in_array($args['art'] ?? null, ['impuls', 'neuigkeit'], true) ? $args['art'] : 'impuls',
            'title' => $titel, 'slug' => $slug, 'excerpt' => $args['kurz'] ?? null, 'body' => $html, 'image_url' => $args['bild_url'] ?? null,
            'source' => 'app', 'visibility' => 'members', 'notify_channels' => array_values(array_intersect((array) ($args['bescheid'] ?? []), ['push', 'mail'])),
            'is_published' => (bool) ($args['veroeffentlichen'] ?? false), 'published_at' => now(),
        ]);

        return ['post_id' => $post->id, 'titel' => $post->title, 'veroeffentlicht' => $post->is_published, 'url' => route('impulse.show', $post), 'bearbeiten' => url('/coach/posts/'.$post->id.'/bearbeiten')];
    }
}
