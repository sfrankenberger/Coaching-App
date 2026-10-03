<?php

namespace Tests\Feature;

use App\Ai\Anthropic;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicTest extends TestCase
{
    use RefreshDatabase;

    public function test_mindestrahmen_und_nachfassen_wenn_das_denken_den_rahmen_aufbraucht(): void
    {
        $a = Tenant::create(['slug' => 'a', 'name' => 'A', 'settings' => ['ai' => ['anthropic_key' => 'sk-test']]]);
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['stop_reason' => 'max_tokens', 'content' => [['type' => 'thinking', 'thinking' => '...']], 'usage' => ['output_tokens' => 1024]])
                ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'thinking', 'thinking' => '...'], ['type' => 'text', 'text' => '{"motiv":"Ein Koffer"}']], 'usage' => ['output_tokens' => 90]]),
        ]);
        $r = app(CurrentTenant::class)->run($a, fn () => app(Anthropic::class)->json('Motiv?', null, 200));
        $this->assertSame('Ein Koffer', $r['data']['motiv']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['max_tokens'] === 1024);
        Http::assertSent(fn ($req) => $req['max_tokens'] === 4096);
    }
}
