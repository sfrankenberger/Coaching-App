<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Nachricht;
use App\Support\Bildkarte;
use App\Tenancy\Branding;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Rundsendung;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Filament\Coach\Pages\Rundnachricht as RundnachrichtSeite;
use App\Models\Rundnachricht;
use Livewire\Livewire;
use Tests\TestCase;

class RundsendungTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected User $lea;

    protected User $anna;

    protected User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->a = Tenant::create(['slug' => 'a', 'name' => 'A']);
        $this->a->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
        $this->lea = User::factory()->create(['name' => 'Lea Coach']);
        $this->anna = User::factory()->create(['name' => 'Anna Muster']);
        $this->bea = User::factory()->create(['name' => 'Bea Beispiel']);
        $this->a->users()->attach($this->lea, ['role' => Role::Owner->value, 'status' => 'active']);
        $this->a->users()->attach($this->anna, ['role' => Role::Member->value, 'status' => 'active']);
        $this->a->users()->attach($this->bea, ['role' => Role::Member->value, 'status' => 'active']);
    }

    protected function in(callable $fn): mixed
    {
        return app(CurrentTenant::class)->run($this->a, $fn);
    }

    public function test_an_alle_und_an_ein_programm_mit_gruppenchat(): void
    {
        $r = $this->in(fn () => app(Rundsendung::class)->send(['an' => 'alle', 'titel' => 'Hallo zusammen', 'text' => 'Morgen geht es los.', 'kanaele' => ['push', 'mail']], $this->lea));
        $this->assertSame(2, $r['empfaenger']);
        $this->assertSame(2, $r['erreicht'], 'ohne Push per Mail');
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Hallo zusammen' && in_array('mail', $n->channels, true));
        Notification::assertNotSentTo($this->lea, AppNotification::class);

        $kurs = $this->in(function () {
            $kurs = Program::create(['title' => 'Kurs', 'slug' => 'kurs']);
            ProgramMember::create(['program_id' => $kurs->id, 'user_id' => $this->anna->id]);
            PushSubscription::create(['user_id' => $this->bea->id, 'endpoint' => 'https://push.test/x', 'endpoint_hash' => sha1('https://push.test/x'), 'p256dh' => 'a', 'auth' => 'b']);

            return $kurs;
        });
        $r = $this->in(fn () => app(Rundsendung::class)->send(['an' => 'programm', 'program_id' => $kurs->id, 'titel' => 'Nur Kurs', 'text' => 'Text', 'kanaele' => ['push'], 'chat' => true], $this->lea));
        $this->assertSame(1, $r['empfaenger']);
        $this->assertTrue($r['chat']);
        $this->assertSame(0, $r['erreicht'], 'Anna hat kein Push und Mail ist aus');
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);
        Notification::assertSentToTimes($this->bea, AppNotification::class, 1);
        $this->in(fn () => $this->assertSame(1, Message::where('user_id', $this->lea->id)->where('body', 'like', 'Nur Kurs%')->count()));
    }

    public function test_mail_an_alle_auch_mit_push(): void
    {
        $this->in(fn () => PushSubscription::create(['user_id' => $this->anna->id, 'endpoint' => 'https://push.test/anna', 'endpoint_hash' => sha1('https://push.test/anna'), 'p256dh' => 'a', 'auth' => 'b']));
        $this->in(fn () => app(Rundsendung::class)->send(['an' => 'alle', 'titel' => 'Umzug', 'text' => 'Neue Adresse.', 'kanaele' => ['push', 'mail'], 'mail_alle' => true], $this->lea));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Umzug' && $n->nachricht->mailImmer && in_array('mail', $n->channels, true) && in_array(\App\Notifications\WebPushChannel::class, $n->channels, true));
    }

    public function test_seite_entwurf_testmail_protokoll_und_uebergang_aus_einstellungen(): void
    {
        $this->actingAs($this->lea);
        // Uebergang: Entwurf aus den Einstellungen wird beim Oeffnen zum Eintrag
        $this->a->forceFill(['settings' => array_merge($this->a->settings ?? [], ['rundnachricht' => ['entwurf' => ['an' => 'alle', 'titel' => 'Aus Einstellungen', 'text' => 'Alter Entwurf', 'kanaele' => ['mail'], 'mail_alle' => true]]])])->save();
        $this->in(function () {
            Livewire::test(RundnachrichtSeite::class)->assertFormSet(['titel' => 'Aus Einstellungen', 'mail_alle' => true]);
            $this->assertSame(1, Rundnachricht::where('status', 'entwurf')->count());
            $this->assertNull($this->a->fresh()->setting('rundnachricht.entwurf'));

            // Neuer Entwurf speichern
            Livewire::test(RundnachrichtSeite::class)->call('neu')
                ->fillForm(['an' => 'alle', 'titel' => 'Entwurf eins', 'text' => 'Hallo {vorname}', 'kanaele' => ['push', 'mail']])
                ->callAction('entwurf')->assertNotified('Entwurf gespeichert');
            $this->assertSame(2, Rundnachricht::where('status', 'entwurf')->count());
            $neu = Rundnachricht::where('titel', 'Entwurf eins')->first();

            // Testmail: nur an mich, immer per Mail, nicht in die Glocke
            Livewire::test(RundnachrichtSeite::class)->call('laden', $neu->id)->assertFormSet(['titel' => 'Entwurf eins'])->callAction('test', ['an' => $this->lea->id])->assertNotified('Testmail unterwegs');
            Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => $n->nachricht->titel === 'Entwurf eins' && $n->nachricht->mailBetreff === '[Test] Entwurf eins' && $n->nachricht->mailImmer && $n->nachricht->text === 'Hallo Lea');
            // An eine Person aus dem Team, nicht an eine Teilnehmerin
            $andrea = User::factory()->create(['name' => 'Andrea Team', 'email' => 'andrea@test.ch']);
            $this->a->users()->attach($andrea, ['role' => Role::Team->value, 'status' => 'active']);
            Livewire::test(RundnachrichtSeite::class)->call('laden', $neu->id)->callAction('test', ['an' => $andrea->id])->assertNotified('Testmail unterwegs');
            Notification::assertSentTo($andrea, AppNotification::class, fn (AppNotification $n) => $n->nachricht->text === 'Hallo Andrea');
            Livewire::test(RundnachrichtSeite::class)->call('laden', $neu->id)->callAction('test', ['an' => $this->anna->id]);
            Notification::assertNotSentTo($this->anna, AppNotification::class);

            // Senden: Entwurf wird zum Protokolleintrag
            Livewire::test(RundnachrichtSeite::class)->call('laden', $neu->id)->callAction('senden')->assertNotified();
            $this->assertSame(1, Rundnachricht::where('status', 'entwurf')->count(), 'der andere Entwurf bleibt');
            $g = Rundnachricht::where('status', 'gesendet')->first();
            $this->assertSame($neu->id, $g->id);
            $this->assertSame(2, $g->empfaenger);
            $this->assertNotNull($g->sent_at);
            $this->assertSame($this->lea->id, $g->user_id);

            // Protokoll auf der Seite, Entwurf loeschen
            Livewire::test(RundnachrichtSeite::class)->assertSee('Entwurf eins')->assertSee('2 von 2')->call('loeschen', Rundnachricht::where('status', 'entwurf')->value('id'));
            $this->assertSame(0, Rundnachricht::where('status', 'entwurf')->count());
        });
    }

    public function test_an_einzelne_persoenlich_ins_1_zu_1(): void
    {
        $andrea = User::factory()->create(['name' => 'Andrea Team']);
        $this->a->users()->attach($andrea, ['role' => Role::Team->value, 'status' => 'active']);
        $fremd = User::factory()->create();

        $r = $this->in(fn () => app(Rundsendung::class)->send([
            'an' => 'einzelne', 'user_ids' => [$this->anna->id, $fremd->id], 'text' => 'Hallo {vorname}, wie geht es dir?', 'persoenlich' => true,
        ], $this->lea));

        $this->assertSame(1, $r['empfaenger'], 'nur aktive Personen dieses Mandanten');
        $this->assertSame(1, $r['persoenlich']);
        $this->in(function () {
            $m = Message::first();
            $this->assertSame('Hallo Anna, wie geht es dir?', $m->body);
            $this->assertSame($this->anna->id, $m->conversation->user_id);
        });
        Notification::assertSentToTimes($this->anna, AppNotification::class, 1);
        Notification::assertNotSentTo($this->bea, AppNotification::class);
        Notification::assertNotSentTo($andrea, AppNotification::class, 'das Team bekommt die eigene Nachricht nicht gemeldet');
    }

    public function test_mail_mit_bausteinen_titel_ueber_anrede_und_protokoll(): void
    {
        $bloecke = [
            ['type' => 'text', 'data' => ['html' => '<p>Liebe {vorname}, das ist <strong>wichtig</strong>.</p><ul><li>eins</li></ul>']],
            ['type' => 'knopf', 'data' => ['text' => 'Zur App', 'url' => 'https://a.test/', 'stil' => 'voll', 'ausrichtung' => 'mitte']],
        ];
        $html = $this->in(fn () => view('mail.nachricht', [
            'user' => $this->anna,
            'nachricht' => new Nachricht(titel: 'Umzug', text: 'Kurzer Pushtext', bloecke: $bloecke),
            'branding' => app(Branding::class), 'appName' => 'A',
        ])->render());
        $this->assertStringContainsString('<strong>wichtig</strong>', $html);
        $this->assertStringContainsString('Liebe Anna', $html, '{vorname} aus den Bausteinen');
        $this->assertStringContainsString('<li', $html);
        $this->assertStringNotContainsString('Kurzer Pushtext', $html, 'mit Bausteinen zeigt die Mail den Text nicht');
        $this->assertLessThan(strpos($html, 'Hallo Anna'), strpos($html, '<h1'), 'Titel steht ueber der Anrede');

        // ohne Bausteine: Text, Titel ueber der Anrede
        $html = $this->in(fn () => view('mail.nachricht', ['user' => $this->anna, 'nachricht' => new Nachricht(titel: 'Nur Text', text: 'Kurzer Pushtext'), 'branding' => app(Branding::class), 'appName' => 'A'])->render());
        $this->assertStringContainsString('Kurzer Pushtext', $html);
        $this->assertLessThan(strpos($html, 'Hallo Anna'), strpos($html, 'Nur Text'));

        // Versand: Bausteine gehen mit der Nachricht und stehen im Protokoll, leere Zeilen fallen weg
        $this->in(fn () => app(Rundsendung::class)->send(['an' => 'alle', 'titel' => 'Umzug', 'text' => 'Kurz', 'kanaele' => ['mail'], 'bloecke' => ['x' => $bloecke[0], 'y' => ['type' => '', 'data' => []]]], $this->lea));
        Notification::assertSentTo($this->anna, AppNotification::class, fn (AppNotification $n) => count($n->nachricht->bloecke ?? []) === 1 && $n->nachricht->text === 'Kurz');
        $this->in(fn () => $this->assertCount(1, Rundnachricht::where('status', 'gesendet')->first()->bloecke));
    }

    public function test_bildkarte_und_bild_in_der_rundnachricht(): void
    {
        Storage::fake('local');
        $png = $this->in(fn () => Bildkarte::png('Deine App zieht um, alles ist schon dort', 'Lea'));
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        [$w, $h] = getimagesizefromstring($png);
        $this->assertSame([1200, 630], [$w, $h]);

        $this->actingAs($this->lea);
        $this->in(function () {
            $c = Livewire::test(RundnachrichtSeite::class)->call('neu')
                ->fillForm(['an' => 'alle', 'titel' => 'Umzug', 'text' => "Erster Absatz\n\nZweiter Absatz", 'kanaele' => ['mail']])
                ->call('textAlsBaustein')
                ->call('bildErzeugen', ['satz' => 'Alles an einem neuen Ort', 'unterzeile' => 'Lea'])->assertNotified('Bild eingesetzt');
            $bloecke = array_values($c->get('data.bloecke'));
            $this->assertSame(['bild', 'text'], array_column($bloecke, 'type'), 'Bild oben, Text darunter');
            $this->assertSame('Alles an einem neuen Ort', $bloecke[0]['data']['alt']);
            $pfad = reset($bloecke[0]['data']['datei']);
            $this->assertStringStartsWith('tenants/'.$this->a->id.'/newsletter/karte-', $pfad);
            Storage::disk('local')->assertExists($pfad);
            $this->assertStringContainsString('<p>Erster Absatz</p><p>Zweiter Absatz</p>', $bloecke[1]['data']['html']);

            // Entwurf speichern und wieder laden: Bausteine bleiben
            $c->callAction('entwurf')->assertNotified('Entwurf gespeichert');
            $e = Rundnachricht::where('status', 'entwurf')->first();
            $this->assertSame(['bild', 'text'], array_column($e->bloecke, 'type'));
            // Testmail traegt die Bausteine, das Bild ist ueber die App erreichbar
            Livewire::test(RundnachrichtSeite::class)->call('laden', $e->id)->callAction('test', ['an' => $this->lea->id])->assertNotified('Testmail unterwegs');
            Notification::assertSentTo($this->lea, AppNotification::class, fn (AppNotification $n) => ($n->nachricht->bloecke[0]['type'] ?? null) === 'bild');
            $this->assertStringContainsString('/n/bild/'.basename($pfad), \App\Newsletter\Bausteine::html($e->bloecke, null));
        });
    }

    public function test_kurse_ohne_1_zu_1_und_alle_in_der_begleitung(): void
    {
        $andrea = User::factory()->create(['name' => 'Andrea Team']);
        $this->a->users()->attach($andrea, ['role' => Role::Team->value, 'status' => 'active']);
        $ehemalig = User::factory()->create(['name' => 'Ehemalige']);
        $this->a->users()->attach($ehemalig, ['role' => Role::Member->value, 'status' => 'inactive']);
        $this->in(function () use ($andrea, $ehemalig) {
            $hybrid = Program::create(['title' => 'Hybrid', 'slug' => 'hybrid', 'type' => 'hybrid']);
            $intern = Program::create(['title' => 'Intern', 'slug' => 'intern', 'type' => 'selfpaced', 'is_internal' => true]);
            $leer = Program::create(['title' => 'Leer', 'slug' => 'leer', 'type' => 'selfpaced']);
            $eins = Program::create(['title' => '1:1 Anna', 'slug' => 'eins-anna', 'type' => 'one_on_one']);
            $zwei = Program::create(['title' => '1:1 Ehemalige', 'slug' => 'eins-ehemalig', 'type' => 'one_on_one']);
            foreach ([[$hybrid, $this->anna], [$hybrid, $andrea], [$hybrid, $this->lea], [$intern, $this->anna], [$eins, $this->anna], [$zwei, $ehemalig]] as [$p, $u]) {
                ProgramMember::create(['program_id' => $p->id, 'user_id' => $u->id]);
            }
            $this->assertSame(['Hybrid'], Rundsendung::kurse()->pluck('title')->all(), 'keine 1:1, nichts Internes, nichts ohne Teilnehmerinnen');

            $r = app(Rundsendung::class);
            $this->assertSame([$this->anna->id], $r->recipients('programm', $hybrid->id, $this->lea)->all(), 'Team und Absenderin nicht, auch wenn sie im Kurs sind');
            $this->assertSame([$this->anna->id], $r->recipients('begleitung', null, $this->lea)->all(), 'alle 1:1 zusammen, Ehemalige ohne aktives Konto nicht');
            $this->assertSame([$this->anna->id, $this->bea->id], $r->recipients('alle', null, $this->lea)->sort()->values()->all());

            $this->actingAs($this->lea);
            Livewire::test(RundnachrichtSeite::class)->call('neu')
                ->fillForm(['an' => 'begleitung', 'titel' => 'Für die 1:1', 'text' => 'Hallo', 'kanaele' => ['mail']])
                ->callAction('senden')->assertNotified('An 1 Person geschickt');
            $this->assertSame('1:1 Begleitung', Rundnachricht::where('status', 'gesendet')->first()->wohin());
        });
    }
}
