<?php

namespace App\Content;

use App\Models\PodcastEpisode;
use App\Models\Post;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Impulse und Podcastfolgen per RSS holen. Feeds stehen je Mandant in
 * tenants.settings.feeds: [{type: post|podcast, url, show, limit}].
 * Idempotent: Beitraege ueber legacy_id "feed:<guid>", Folgen ueber guid.
 */
class FeedImport
{
    public array $stats = ['beitraege' => 0, 'folgen' => 0, 'fehler' => []];

    public function __construct(protected Tenant $tenant) {}

    public function run(?array $feeds = null): array
    {
        $feeds ??= (array) $this->tenant->setting('feeds', []);
        foreach ($feeds as $feed) {
            if (empty($feed['url'])) {
                continue;
            }
            try {
                $xml = $this->fetch($feed['url']);
                $items = $this->items($xml);
                $limit = (int) ($feed['limit'] ?? 0);
                if ($limit > 0) {
                    $items = array_slice($items, 0, $limit);
                }
                $channelImage = $this->channelImage($xml);
                foreach ($items as $it) {
                    $it['image'] = $it['image'] ?: $channelImage;
                    ($feed['type'] ?? 'post') === 'podcast' ? $this->episode($it, $feed) : $this->post($it, $feed);
                }
            } catch (\Throwable $e) {
                $this->stats['fehler'][] = ($feed['url'] ?? '?').': '.$e->getMessage();
            }
        }

        return $this->stats;
    }

    protected function fetch(string $url): SimpleXMLElement
    {
        $r = Http::timeout(30)->withUserAgent('CoachingApp/1.0 (+feed)')->get($url);
        if (! $r->successful()) {
            throw new \RuntimeException('HTTP '.$r->status());
        }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($r->body(), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (! $xml) {
            throw new \RuntimeException('Feed nicht lesbar');
        }

        return $xml;
    }

    protected function channelImage(SimpleXMLElement $xml): ?string
    {
        $itunes = $xml->channel?->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
        if ($itunes && isset($itunes->image)) {
            $href = (string) ($itunes->image->attributes()['href'] ?? '');
            if ($href) {
                return $href;
            }
        }
        $u = (string) ($xml->channel?->image?->url ?? '');

        return $u ?: null;
    }

    /** RSS 2.0 (channel/item) oder Atom (entry) auf eine gemeinsame Form bringen. */
    public function items(SimpleXMLElement $xml): array
    {
        $out = [];
        $entries = isset($xml->channel) ? $xml->channel->item : $xml->entry;
        foreach ($entries as $it) {
            $itunes = $it->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
            $content = $it->children('http://purl.org/rss/1.0/modules/content/');
            $media = $it->children('http://search.yahoo.com/mrss/');
            $enc = isset($it->enclosure) ? $it->enclosure->attributes() : null;
            $encType = (string) ($enc['type'] ?? '');
            $body = isset($content->encoded) ? (string) $content->encoded : (string) ($it->description ?? $it->content ?? $it->summary ?? '');
            $image = null;
            if (isset($itunes->image)) {
                $image = (string) ($itunes->image->attributes()['href'] ?? '') ?: null;
            }
            if (! $image && isset($media->content)) {
                foreach ($media->content as $m) {
                    $a = $m->attributes();
                    if (str_starts_with((string) ($a['type'] ?? ''), 'image') || str_starts_with((string) ($a['medium'] ?? ''), 'image')) {
                        $image = (string) $a['url'];
                        break;
                    }
                }
            }
            if (! $image && isset($media->thumbnail)) {
                $image = (string) ($media->thumbnail->attributes()['url'] ?? '') ?: null;
            }
            if (! $image && $enc && str_starts_with($encType, 'image/')) {
                $image = (string) $enc['url'];
            }
            if (! $image && preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $body, $m)) {
                $image = $m[1];
            }
            $link = (string) ($it->link ?? '');
            if ($link === '' && isset($it->link['href'])) {
                $link = (string) $it->link['href'];
            }
            $date = (string) ($it->pubDate ?? $it->published ?? $it->updated ?? '');
            $cats = [];
            foreach ($it->category as $c) {
                $name = trim((string) ($c['term'] ?? $c));
                if ($name !== '') {
                    $cats[] = $name;
                }
            }
            $out[] = [
                'guid' => (string) ($it->guid ?? $it->id ?? $link),
                'title' => html_entity_decode(trim((string) $it->title), ENT_QUOTES, 'UTF-8'),
                'link' => $link,
                'date' => $date ? Carbon::parse($date)->utc() : null,
                'excerpt' => trim(strip_tags((string) ($itunes->subtitle ?? $itunes->summary ?? $it->description ?? ''))),
                'body' => $body,
                'image' => $image,
                'audio_url' => $enc && str_starts_with($encType, 'audio') ? (string) $enc['url'] : null,
                'duration' => isset($itunes->duration) ? (string) $itunes->duration : null,
                'episode' => isset($itunes->episode) ? (int) $itunes->episode : null,
                'categories' => array_values(array_unique($cats)),
            ];
        }

        return $out;
    }

    protected function post(array $it, array $feed): void
    {
        if ($it['title'] === '') {
            return;
        }
        $post = Post::firstOrNew(['legacy_id' => 'feed:'.sha1($it['guid'])]);
        $post->fill([
            'type' => $feed['post_type'] ?? 'impuls',
            'title' => $it['title'],
            'excerpt' => Str::limit($it['excerpt'], 400) ?: null,
            'body' => $it['body'] ?: null,
            'image_url' => $it['image'],
            'url' => $it['link'] ?: null,
            'source' => 'feed',
            'categories' => $it['categories'] ?: null,
            'visibility' => $feed['visibility'] ?? 'members',
            'published_at' => $it['date'] ?? $post->published_at ?? now(),
            'is_published' => $post->exists ? $post->is_published : true,
        ]);
        if (! $post->exists) {
            $post->slug = Post::uniqueSlug($it['title']);
        }
        $post->save();
        $this->stats['beitraege']++;
    }

    protected function episode(array $it, array $feed): void
    {
        if ($it['title'] === '' || ! $it['audio_url']) {
            return;
        }
        $e = PodcastEpisode::firstOrNew(['guid' => $it['guid']]);
        $e->fill([
            'show' => $feed['show'] ?? 'Podcast',
            'title' => $it['title'],
            'episode_number' => $it['episode'] ?: (preg_match('~#\s*(\d+)|folge\s*(\d+)~i', $it['title'], $m) ? (int) ($m[1] ?: ($m[2] ?? 0)) ?: null : null),
            'excerpt' => Str::limit($it['excerpt'], 400) ?: null,
            'body' => $it['body'] ?: null,
            'audio_url' => $it['audio_url'],
            'image_url' => $it['image'],
            'url' => $it['link'] ?: null,
            'duration_seconds' => PodcastEpisode::parseDuration($it['duration']),
            'published_at' => $it['date'] ?? $e->published_at ?? now(),
            'keywords' => $e->keywords ?: ($it['categories'] ?: null),
            'is_published' => $e->exists ? $e->is_published : true,
        ]);
        $e->save();
        $this->stats['folgen']++;
    }
}
