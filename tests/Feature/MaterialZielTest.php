<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Material, das in der App selbst liegt: Ziel in der App (Arbeitsbuch als Kurs) und Textseiten mit Videos. */
class MaterialZielTest extends TestCase
{
    use RefreshDatabase;

    public function test_ziel_in_der_app_und_textseite(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active']);
        [$link, $text, $wb] = app(CurrentTenant::class)->run($a, function () use ($anna) {
            $k = Program::create(['slug' => 'kurs', 'title' => 'Kurs']);
            ProgramMember::create(['program_id' => $k->id, 'user_id' => $anna->id]);
            $wb = Program::create(['slug' => 'workbook', 'title' => 'Arbeitsbuch', 'type' => 'workbook']);
            ProgramMember::create(['program_id' => $wb->id, 'user_id' => $anna->id]);
            $link = Resource::create(['title' => 'Workbook interaktiv', 'type' => 'link', 'url' => 'https://alt.example/mitgliederbereich/workbook/', 'settings' => ['ziel' => '/kurse/workbook']]);
            $link->links()->create(['resourceable_type' => 'program', 'resourceable_id' => $k->id]);
            $text = Resource::create(['title' => 'Die Prozessschritte als Video', 'type' => 'text', 'body' => '<h2><span class="nummer">0</span>Die Entscheidung</h2><div class="video"><iframe src="https://player.vimeo.com/video/1?h=x"></iframe></div><p>Am Anfang steht ein Ja.</p>']);
            $text->links()->create(['resourceable_type' => 'program', 'resourceable_id' => $k->id]);

            return [$link, $text, $wb];
        });

        // Liste: Ziel in der App ohne neues Fenster, Textseite mit eigener Seite
        $r = $this->actingAs($anna)->get('http://a.test/material')->assertOk();
        $r->assertSee('href="http://a.test/kurse/workbook"', false)->assertDontSee('alt.example');
        $r->assertSee('href="http://a.test/material/'.$text->id.'"', false);
        $this->assertStringNotContainsString('workbook" target="_blank"', $r->getContent());

        // Ziel-Link oeffnet den Kurs, Textseite zeigt Text und Video
        $this->actingAs($anna)->get('http://a.test/material/'.$link->id.'/datei')->assertRedirect('http://a.test/kurse/workbook');
        $this->actingAs($anna)->get('http://a.test/kurse/workbook')->assertOk();
        $this->actingAs($anna)->get('http://a.test/material/'.$text->id)->assertOk()->assertSee('Die Entscheidung')->assertSee('player.vimeo.com/video/1')->assertSee('Am Anfang steht ein Ja.');
    }
}
