<?php

namespace Tests\Feature;

use App\Chat\Chat;
use App\Enums\Role;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoacheesTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_sieht_ampel_liste_und_auskunft_die_anderen_nicht(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $lea = User::factory()->create(['name' => 'Lea Coach']);
        $a->users()->attach($lea, ['role' => Role::Owner->value, 'status' => 'active', 'settings' => json_encode(['onboarding_seen_at' => now()->toIso8601String()])]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'last_seen_at' => now()]);
        $kim = User::factory()->create(['name' => 'Kim Kontakt']);
        $a->users()->attach($kim, ['role' => Role::Member->value, 'status' => 'active']);
        app(CurrentTenant::class)->run($a, function () use ($anna) {
            $p = Program::create(['slug' => 'k', 'title' => 'Kurs K']);
            ProgramMember::create(['program_id' => $p->id, 'user_id' => $anna->id]);
            $conv = app(Chat::class)->directFor($anna);
            Message::create(['conversation_id' => $conv->id, 'user_id' => $anna->id, 'body' => 'Hallo?']);
            $conv->forceFill(['last_message_at' => now()])->save();
        });

        $r = $this->actingAs($lea)->get('http://a.test/coachees')->assertOk();
        $r->assertSee('Wie stehen sie gerade da')->assertSee('wartet auf deine Antwort')->assertSee('kurz nachfragen')
            ->assertSee('1 in Begleitung')->assertSee('1 weitere Kontakte')->assertSee('Anna Muster')->assertSee('weitere Kontakte anzeigen');
        $this->actingAs($lea)->get('http://a.test/coachees?q=kim&sort=name')->assertOk()->assertSee('Kim Kontakt')->assertSee('0 in Begleitung')->assertSee('1 weitere Kontakte');
        $this->actingAs($anna)->get('http://a.test/coachees')->assertForbidden();

        config(['ai.anthropic_key' => null]);
        $this->actingAs($lea)->post('http://a.test/coachees/frage', ['frage' => 'Was macht Anna Muster?'])->assertOk()->assertSee('Anna Muster')->assertSee('Kurs K')->assertSee('Dossier öffnen');
        $this->actingAs($lea)->post('http://a.test/coachees/frage', ['frage' => 'Wo finde ich die Termine?'])->assertOk()->assertSee('Termine und Aufzeichnungen');
        $this->actingAs($anna)->post('http://a.test/coachees/frage', ['frage' => 'Was macht Anna?'])->assertForbidden();
    }
}
