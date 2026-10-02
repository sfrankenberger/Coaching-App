<?php

namespace Tests\Feature;

use App\Ai\Assistent;
use App\Chat\Chat;
use App\Coach\Ansicht;
use App\Enums\Role;
use App\Models\Answer;
use App\Models\Event;
use App\Models\Exercise;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wissen;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Drei Sichten: Arbeitsplatz fuer das Team, "wie eine Teilnehmerin", und das einfache Menue fuer Teilnehmerinnen. */
class ArbeitsplatzTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A', 'timezone' => 'Europe/Zurich']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_team_sieht_arbeitsliste_und_kann_umschalten(): void
    {
        $this->in(function () {
            $p = Program::create(['slug' => 'k', 'title' => 'Kurs K', 'is_published' => true]);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $this->anna->id]);
            $conv = app(Chat::class)->directFor($this->anna);
            Message::create(['conversation_id' => $conv->id, 'user_id' => $this->anna->id, 'body' => 'Kannst du mir helfen?']);
            $conv->forceFill(['last_message_at' => now()])->save();
            Question::create(['program_id' => $p->id, 'user_id' => $this->anna->id, 'title' => 'Wie geht Woche 2?']);
            // Anna teilt Antworten in zwei Einheiten und eine Reflexion: auf der Liste eine Zeile
            $u1 = Unit::create(['program_id' => $p->id, 'title' => 'Dein Lebensrad', 'position' => 1, 'is_published' => true]);
            $u2 = Unit::create(['program_id' => $p->id, 'title' => 'Dein Umfeld', 'position' => 2, 'is_published' => true]);
            foreach ([[$u1, 3], [$u2, 2]] as [$u, $n]) {
                for ($i = 0; $i < $n; $i++) {
                    $ex = Exercise::create(['unit_id' => $u->id, 'type' => 'text', 'prompt' => "Frage {$i}", 'position' => $i]);
                    Answer::create(['user_id' => $this->anna->id, 'exercise_id' => $ex->id, 'value' => ['v' => 'Antwort'], 'shared_with_coach' => true]);
                }
            }
            Reflection::create(['user_id' => $this->anna->id, 'week_label' => 'Woche 40', 'went_well' => 'Gut', 'program_id' => $p->id, 'visibility' => 'program', 'shared_at' => now()]);
            Event::create(['title' => 'Call morgen', 'type' => 'group_call', 'program_id' => $p->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'is_published' => true]);
        });
        // Bea ist seit zwei Wochen nicht mehr da, Carla seit gestern neu dabei
        $bea = User::factory()->create(['name' => 'Bea Still']);
        $this->a->users()->attach($bea, ['role' => Role::Member->value, 'status' => 'active', 'joined_at' => now()->subMonth(), 'last_seen_at' => now()->subDays(15)]);
        $carla = User::factory()->create(['name' => 'Carla Neu']);
        $this->a->users()->attach($carla, ['role' => Role::Client->value, 'status' => 'active', 'joined_at' => now()->subDay()]);

        $r = $this->actingAs($this->lea)->get('http://a.test/')->assertOk();
        $r->assertSee('Guten Tag, Lea')->assertSee('3 Dinge warten')
            ->assertSee('Mit dir geteilt')->assertSee('Anna Muster hat 5 Antworten in 2 Einheiten und eine Reflexion geteilt')->assertSee('Dein Lebensrad, Dein Umfeld, Woche 40')
            ->assertSee('Wartet auf deine Antwort')->assertSee('Kannst du mir helfen?')
            ->assertSee('Fragen ohne Antwort')->assertSee('Wie geht Woche 2?')
            ->assertDontSee('Wartet auf Freigabe')
            ->assertSee('Lange nichts gehört')->assertSee('Bea Still')->assertSee('seit 15 Tagen nicht da')->assertSee('Nachfragen')
            ->assertSee('Neu dabei')->assertSee('Carla Neu')
            ->assertSee('Als Nächstes')->assertSee('Call morgen')
            ->assertSee('Letzte sieben Tage')->assertSee('neue Person')
            ->assertDontSee('fa-sliders"></i></a>', false)   // Verwaltung nicht im Kopf, nur im Menue
            ->assertDontSee('chat-knopf')
            ->assertSee('Heute')->assertSee('Coachees')->assertSee('Wie eine Teilnehmerin')->assertSee('modus-team', false)
            ->assertDontSee('Mein Journal');

        // Als gelesen: Anna wartet nicht mehr
        $m = $this->in(fn () => Membership::where('user_id', $this->anna->id)->first());
        $this->actingAs($this->lea)->post("http://a.test/coachees/{$m->id}/gelesen")->assertRedirect();
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertDontSee('Wartet auf deine Antwort')->assertSee('2 Dinge warten');

        // Umschalten: wie eine Teilnehmerin, dann zurueck
        $this->actingAs($this->lea)->post('http://a.test/ansicht', ['ansicht' => 'teilnehmer'])->assertRedirect('http://a.test');
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertSee('Hallo Lea')->assertDontSee('Für dich als Coach')->assertSee('Zurück zum Arbeitsplatz')->assertSee('Meine Sachen')
            ->assertDontSee('Meine Zeitleiste')->assertDontSee('Meine Projekte')   // vorerst ausgeschaltet
            ->assertSee('modus-coachee', false)->assertSee('Als Teilnehmerin');   // Kennzeichnung im Kopf
        // In der Teilnehmer-Ansicht fuehrt das Gespraech zum eigenen 1:1, nicht zur Liste aller Gespraeche
        $eigenes = $this->in(fn () => app(Chat::class)->directFor($this->lea));
        $this->actingAs($this->lea)->get('http://a.test/gespraech')->assertRedirect('http://a.test/gespraech/'.$eigenes->id);
        $this->assertSame(0, $this->in(fn () => app(Chat::class)->unreadFor($this->lea)), 'Annas wartende Nachricht zaehlt in der Teilnehmer-Ansicht nicht');
        // Im eigenen Gespraech: kein Knopf zur Team-Liste, dafuer die Karte mit Sitzungen und Buchung wie bei einer Teilnehmerin
        $this->actingAs($this->lea)->get('http://a.test/gespraech/'.$eigenes->id)->assertOk()->assertDontSee('Alle Gespräche')->assertSee('1:1 mit');
        // Termine: nur die eigenen und die der eigenen Kurse, nicht alle im Mandanten
        $this->in(fn () => Event::create(['title' => 'Fremder Call', 'starts_at' => now()->addDays(2), 'user_id' => $this->anna->id, 'is_published' => true]));
        $this->actingAs($this->lea)->get('http://a.test/termine')->assertOk()->assertDontSee('Fremder Call');
        $this->actingAs($this->lea)->post('http://a.test/ansicht', ['ansicht' => 'arbeitsplatz']);
        $this->actingAs($this->lea)->get('http://a.test/termine')->assertOk()->assertSee('Fremder Call');
        $this->actingAs($this->lea)->post('http://a.test/ansicht', ['ansicht' => 'teilnehmer']);
        $this->actingAs($this->lea)->get('http://a.test/journal')->assertRedirect('http://a.test');
        $this->actingAs($this->lea)->get('http://a.test/projekte')->assertRedirect('http://a.test');
        // Der aeltere Wert "teilnehmerin" gilt weiter als Teilnehmer-Ansicht
        $this->lea->membershipIn($this->a)->forceFill(['settings' => ['ansicht' => 'teilnehmerin']])->save();
        $this->assertFalse(Ansicht::arbeitsplatz($this->lea->fresh()));
        // Eingeschaltet: Menuepunkte da
        $this->a->forceFill(['settings' => array_merge($this->a->settings ?? [], ['features' => ['zeitleiste' => true, 'projekte' => true]])])->save();
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertSee('Meine Zeitleiste')->assertSee('Meine Projekte');
        $this->actingAs($this->lea)->get('http://a.test/journal')->assertOk();
        $this->a->forceFill(['settings' => array_merge($this->a->settings ?? [], ['features' => []])])->save();
        $this->actingAs($this->lea)->post('http://a.test/ansicht', ['ansicht' => 'arbeitsplatz'])->assertRedirect();
        $this->actingAs($this->lea)->get('http://a.test/')->assertOk()->assertSee('Guten Tag, Lea');

        // Teilnehmerin: einfaches Menue, kein Umschalter, keine Untermenues unter Nachschlagen
        $this->actingAs($this->anna)->post('http://a.test/ansicht', ['ansicht' => 'arbeitsplatz'])->assertForbidden();
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('Hallo Anna')->assertSee('Übersicht')->assertSee('1:1 Coaching mit')
            ->assertSee('Meine Sachen')->assertSee('Meine Aufgaben')->assertSee('Meine Kurse')->assertSee('Kurs K')->assertSee('Ressourcen')->assertSee('Community')->assertSee('Mein Profil')
            ->assertDontSee('Arbeitsplatz')->assertDontSee('modus-team', false)->assertDontSee('modus-coachee', false)->assertDontSee('Volltext suchen')->assertDontSee('Gemerkt')->assertDontSee('Coachees')->assertDontSee('Dein Bereich')->assertDontSee('class="leiste"', false);
        // Community: alle Fragen aus meinen Kursen
        $this->actingAs($this->anna)->get('http://a.test/community')->assertOk()->assertSee('Wie geht Woche 2?')->assertSee('Was beschäftigt dich?');
        // Profil aufgeraeumt: Benachrichtigungen statt Nachrichten, Hilfe eigene Seite, Mitteilungen nur ueber die Glocke
        $this->actingAs($this->anna)->get('http://a.test/')->assertOk()->assertSee('Benachrichtigungen')->assertDontSee('>Nachrichten<', false)->assertDontSee('>Mitteilungen', false);
        $this->actingAs($this->anna)->get('http://a.test/hilfe')->assertOk()->assertSee('Technik melden');
        $this->actingAs($this->anna)->get('http://a.test/profil')->assertOk()->assertDontSee('Technik melden')->assertSee('In der Community sichtbar');
    }

    public function test_nachschlagen_hat_reiter_statt_untermenue(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/nachschlagen')->assertOk()->assertSee('Finden')->assertSee('Themen')->assertSee('Meine Suchen')->assertSee('Mein Archiv')->assertDontSee('Werkzeuge');
        $this->actingAs($this->anna)->get('http://a.test/nachschlagen?r=themen')->assertOk()->assertSee('Noch keine Themen');
        $this->actingAs($this->lea)->get('http://a.test/nachschlagen?r=werkzeuge')->assertOk()->assertSee('Noch kein Werkzeug');
    }

    public function test_assistent_seite_mit_wissen(): void
    {
        $this->actingAs($this->anna)->get('http://a.test/assistent')->assertForbidden();
        $this->actingAs($this->lea)->get('http://a.test/assistent')->assertOk()->assertSee('Frag mich etwas')->assertSee('Mein Wissen')->assertSee('/api/mcp');
        $this->actingAs($this->lea)->post('http://a.test/assistent/merken', ['body' => 'Erstgespräche dauern 30 Minuten und sind gratis.', 'tags' => 'preise, ablauf'])->assertRedirect('http://a.test/assistent');
        $this->actingAs($this->lea)->get('http://a.test/assistent')->assertOk()->assertSee('Erstgespräche dauern 30 Minuten')->assertSee('preise');
        $this->actingAs($this->lea)->get('http://a.test/assistent?wissen=gratis')->assertOk()->assertSee('Erstgespräche dauern');
        $this->actingAs($this->lea)->get('http://a.test/assistent?wissen=xyzabc')->assertOk()->assertSee('Nichts gefunden');

        // Der Assistent nimmt das Gemerkte in die Fakten
        config(['ai.anthropic_key' => null]);
        $a = $this->in(fn () => app(Assistent::class)->antwort('Wie lange dauert ein Erstgespräch?', $this->lea));
        $this->assertNotNull($a['fehler']);
        $this->assertContains('Erstgespräche dauern 30 Minuten und sind gratis.: Erstgespräche dauern 30 Minuten und sind gratis.', Wissen::fakten('Erstgespräch'));
    }

    public function test_wissen_bleibt_im_mandanten(): void
    {
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $mia = User::factory()->create(['name' => 'Mia B']);
        $b->users()->attach($mia, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->in(fn () => Wissen::merken($this->lea, 'Geheimnis von A'));
        app(CurrentTenant::class)->run($b, function () {
            $this->assertSame(0, Wissen::count());
        });
        $this->actingAs($mia)->get('http://b.test/assistent')->assertOk()->assertDontSee('Geheimnis von A');
    }
}
