<?php

use App\Models;

/*
| Aenderungsprotokoll: welche Modelle protokolliert werden, steht am Modell (Trait
| App\Support\Protokoll\Protokolliert). Hier stehen die gemeinsamen Regeln.
*/
return [
    // Felder, deren Inhalt nie ins Protokoll kommt. Es wird nur festgehalten, DASS sie sich geaendert haben.
    // Je Modell erweiterbar ueber protected static array $protokollSensibel.
    'sensibel' => ['password', 'remember_token', 'phone', 'secret', 'token', 'api_key', 'two_factor_secret'],

    // Felder, deren Aenderung allein keinen Eintrag ausloest und die nicht mitgeschrieben werden.
    // Je Modell erweiterbar ueber protected static array $protokollIgnoriert.
    'ignoriert' => ['created_at', 'updated_at', 'remember_token', 'last_seen_at', 'digest_sent_at', 'reminded_at', 'nudged_at'],

    // Lesbare Namen der Modelle
    'typen' => [
        Models\Membership::class => 'Zugang',
        Models\User::class => 'Person',
        Models\Tenant::class => 'Mandant',
        Models\TenantDomain::class => 'Domain',
        Models\Program::class => 'Programm',
        Models\ProgramStep::class => 'Schritt',
        Models\ProgramMember::class => 'Programm-Teilnahme',
        Models\Unit::class => 'Einheit',
        Models\Exercise::class => 'Übung',
        Models\Question::class => 'Frage',
        Models\Answer::class => 'Antwort',
        Models\Event::class => 'Termin',
        Models\EventAttendee::class => 'Termin-Teilnahme',
        Models\Booking::class => 'Buchung',
        Models\BookingType::class => 'Buchungsart',
        Models\Task::class => 'Aufgabe',
        Models\Note::class => 'Notiz',
        Models\CoachNote::class => 'Coach-Notiz',
        Models\Comment::class => 'Kommentar',
        Models\Reflection::class => 'Reflexion',
        Models\JournalEntry::class => 'Journal',
        Models\Resource::class => 'Material',
        Models\Anhang::class => 'Anhang',
        Models\Offer::class => 'Angebot',
        Models\OfferProduct::class => 'Produktzuordnung',
        Models\Entitlement::class => 'Zugang zum Angebot',
        Models\Verkauf::class => 'Verkauf',
        Models\Post::class => 'Impuls',
        Models\PodcastEpisode::class => 'Podcast-Folge',
        Models\Topic::class => 'Thema',
        Models\Tool::class => 'Werkzeug',
        Models\Wissen::class => 'Wissen',
        Models\Sammlung::class => 'Sammlung',
        Models\Conversation::class => 'Gespräch',
    ],

    // Lesbare Namen der Ereignisse
    'ereignisse' => [
        'created' => 'angelegt',
        'updated' => 'geändert',
        'deleted' => 'gelöscht',
        'restored' => 'wiederhergestellt',
    ],
];
