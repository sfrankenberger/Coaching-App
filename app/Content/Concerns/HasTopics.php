<?php

namespace App\Content\Concerns;

use App\Models\FinderProfile;
use App\Models\Taggable;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Inhalte mit Themen (Themenfinder): Programme, Schritte, Einheiten, Material,
 * Termine, Beitraege, Podcastfolgen. Die Zuordnung liegt in taggables, der
 * kurze Themenfinder-Text (worum, wobei hilft es, Stichworte) in finder_profiles.
 */
trait HasTopics
{
    public function topics(): MorphToMany
    {
        return $this->morphToMany(Topic::class, 'taggable')->using(Taggable::class)->withTimestamps()->orderBy('topics.position')->orderBy('topics.name');
    }

    public function finder(): MorphOne
    {
        return $this->morphOne(FinderProfile::class, 'profilable');
    }

    /** Themen ueber Namen setzen (legt fehlende an). */
    public function syncTopicsByName(iterable $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $ids[] = Topic::findOrCreateByName($name)->id;
        }
        $this->topics()->sync(array_unique($ids));
    }
}
