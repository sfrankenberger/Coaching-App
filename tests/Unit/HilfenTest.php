<?php

namespace Tests\Unit;

use App\Jobs\ConvertAudio;
use App\Models\Tenant;
use App\Support\Kapitel;
use App\Support\Telefon;
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
}
