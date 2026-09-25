<?php

namespace Tests\Unit;

use App\Jobs\ConvertAudio;
use App\Models\Tenant;
use App\Support\Kapitel;
use App\Support\Telefon;
use App\Support\Zeit;
use PHPUnit\Framework\TestCase;

class HilfenTest extends TestCase
{
    public function test_telefon_international(): void
    {
        $ch = new Tenant(['locale' => 'de_CH']);
        $de = new Tenant(['locale' => 'de_DE']);
        $this->assertSame('41791234567', Telefon::international('079 123 45 67', $ch));
        $this->assertSame('491701234567', Telefon::international('0170 1234567', $de));
        $this->assertSame('41791234567', Telefon::international('+41 79 123 45 67', $de));
        $this->assertSame('41791234567', Telefon::international('0041 79 123 45 67', $de));
        $this->assertSame('https://wa.me/41791234567', Telefon::whatsapp('079/123 45 67', $ch));
        $this->assertNull(Telefon::whatsapp('', $ch));
    }

    public function test_kapitel_saeubert_und_macht_sprungmarken(): void
    {
        $html = (string) Kapitel::html('<h3>Einstieg (ab 4:11)</h3><p onclick="x()">Text <script>alert(1)</script><a href="javascript:x">b</a> <span>c</span></p>');
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('<span>', $html);
        $this->assertStringContainsString('<h3>Einstieg <button type="button" class="sprung" data-sprung="251">', $html);
        $this->assertStringContainsString('c</p>', $html);

        $text = (string) Kapitel::html("Zeile 1\nZeile <2> (ab 1:02:03)");
        $this->assertStringContainsString('Zeile 1<br>', $text);
        $this->assertStringContainsString('&lt;2&gt;', $text);
        $this->assertStringContainsString('data-sprung="3723"', $text);
    }

    public function test_welche_audios_umgewandelt_werden(): void
    {
        $this->assertTrue(ConvertAudio::noetig('tenants/1/chat/sprache-1.webm'));
        $this->assertTrue(ConvertAudio::noetig('x.ogg'));
        $this->assertFalse(ConvertAudio::noetig('x.m4a'));
        $this->assertFalse(ConvertAudio::noetig(null));
    }

    public function test_zeit_dauer_lesbar(): void
    {
        $this->assertSame('3 Min.', Zeit::dauerLesbar('03min'));
        $this->assertSame('1 Std. 39 Min.', Zeit::dauerLesbar('1h 39min'));
        $this->assertSame('58 Min.', Zeit::dauerLesbar('58 Min.'));
        $this->assertSame('1 Std. 2 Min.', Zeit::dauerLesbar('1 h 02 min'));
        $this->assertSame('1 Std.', Zeit::dauer(3600));
        $this->assertSame('10 Videos, rund 23 Min.', Zeit::dauerLesbar('10 Videos, rund 23 Min.'));
        $this->assertNull(Zeit::dauerLesbar(''));
    }

    public function test_kapitel_liste(): void
    {
        $html = '<p>Intro</p><h3>Ankommen (ab 00:05)</h3><p>x</p><h3>Ohne Zeit</h3><h3>Übung (ab 1:02:03)</h3>';
        $this->assertSame([['sekunden' => 5, 'titel' => 'Ankommen'], ['sekunden' => 3723, 'titel' => 'Übung']], Kapitel::liste($html));
        $this->assertSame([], Kapitel::liste('nur Text'));
    }
}
