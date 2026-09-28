<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Profilfoto: hochladen, quadratisch abgelegt, im eigenen Mandanten sichtbar, im fremden nicht, loeschen. */
class ProfilFotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_foto_hochladen_anzeigen_und_loeschen(): void
    {
        Storage::fake('local');
        $a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $b = Tenant::create(['slug' => 'b', 'name' => 'B']);
        $b->domains()->create(['domain' => 'b.test', 'is_primary' => true]);
        $anna = User::factory()->create(['name' => 'Anna Muster']);
        $a->users()->attach($anna, ['role' => Role::Member->value, 'status' => 'active', 'settings' => ['onboarding_seen_at' => now()->toIso8601String()]]);
        $mia = User::factory()->create(['name' => 'Mia Muster']);
        $a->users()->attach($mia, ['role' => Role::Member->value, 'status' => 'active']);
        $fremd = User::factory()->create(['name' => 'Fremd']);
        $b->users()->attach($fremd, ['role' => Role::Member->value, 'status' => 'active']);

        // Ohne Foto: Anfangsbuchstabe, kein Bild
        $this->actingAs($anna)->get('http://a.test/profil')->assertOk()->assertSee('Dein Foto')->assertDontSee('/avatar/'.$anna->id);
        $this->actingAs($mia)->get("http://a.test/avatar/{$anna->id}")->assertNotFound();

        // Hochladen: 800x400 wird quadratisch zugeschnitten und als JPEG abgelegt
        $this->actingAs($anna)->post('http://a.test/profil/foto', ['foto' => UploadedFile::fake()->image('ich.png', 800, 400)])
            ->assertRedirect('http://a.test/profil#foto');
        $pfad = "tenants/{$a->id}/avatars/{$anna->id}.jpg";
        Storage::disk('local')->assertExists($pfad);
        $this->assertSame($pfad, $anna->fresh()->avatar_path);
        [$breite, $hoehe] = getimagesizefromstring(Storage::disk('local')->get($pfad));
        $this->assertSame($breite, $hoehe);
        $this->assertSame(400, $breite);

        // Sichtbar fuer Leute im selben Mandanten, nicht im fremden
        $this->actingAs($anna)->get('http://a.test/profil')->assertOk()->assertSee('/avatar/'.$anna->id);
        $this->actingAs($mia)->get("http://a.test/avatar/{$anna->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($fremd)->get("http://b.test/avatar/{$anna->id}")->assertNotFound();

        // Keine Bilddatei: Fehlermeldung, nichts kaputt
        $this->actingAs($anna)->post('http://a.test/profil/foto', ['foto' => UploadedFile::fake()->create('text.pdf', 10, 'application/pdf')])->assertSessionHasErrors('foto');

        // Loeschen
        $this->actingAs($anna)->delete('http://a.test/profil/foto')->assertRedirect('http://a.test/profil#foto');
        Storage::disk('local')->assertMissing($pfad);
        $this->assertNull($anna->fresh()->avatar_path);
    }
}
