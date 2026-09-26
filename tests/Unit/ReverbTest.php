<?php

namespace Tests\Unit;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class ReverbTest extends TestCase
{
    public function test_nachricht_geht_auf_den_privaten_kanal_des_gespraechs(): void
    {
        $m = new Message(['conversation_id' => 7, 'body' => 'Hallo']);
        $m->id = 42;
        $e = new MessageSent($m);

        $this->assertEquals([new PrivateChannel('gespraech.7')], $e->broadcastOn());
        $this->assertSame('nachricht', $e->broadcastAs());
        $this->assertSame(['id' => 42], $e->broadcastWith());
    }
}
