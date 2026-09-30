<?php

namespace Tests\Feature;

use App\Ai\Assistent;
use App\Chat\Chat;
use App\Coach\Ansicht;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Question;
use App\Models\Tenant;
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
            Event::create(['title' => 'Call morgen', 'type' => 'group_call', 'program_id' => $p->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'is_published' => true]);
            Event::create(['title' => 'Alte Aufzeichnung', 'type' => 'group_call', 'program_id' => $p->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour(), 'is_published' => true, 'recording_url' => 'https://vimeo.com/1', 'summary' => 'Zusammenfassung']);
        });

        $r = $this->actingAs($this->lea)->get('http://a.test/')->assertOk();
        $r->assertSee('Guten Tag, Lea')->assertSee('3 Dinge warten')
            ->assertSee('Wartet auf deine Antwort')->assertSee('Kannst du mir helfen?')
            ->assertSee('Fragen ohne Antwort')->assertSee('Wie geht Woche 2?')
            ->assertSee('Wartet auf Freigabe')->assertSee('Alte Aufzeichnung')
            ->assertSee('Als Nächstes')->assertSee('Call morgen')
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
