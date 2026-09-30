<?php

use App\Mail\BackupRestoreCodeMail;
use App\Models\User;
use App\Support\Backup\BackupRestoreConfirmation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** Schickt den Code und liefert ihn aus der abgefangenen Mail zurueck. */
function codeHolen(User $user): string
{
    Mail::fake();
    app(BackupRestoreConfirmation::class)->codeSenden($user);
    $code = null;
    Mail::assertSent(BackupRestoreCodeMail::class, function (BackupRestoreCodeMail $m) use (&$code, $user) {
        $code = $m->code;

        return $m->hasTo($user->email);
    });

    return $code;
}

function fehlerVon(Closure $fn): array
{
    try {
        $fn();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

beforeEach(function () {
    config()->set('app.name', 'Coaching-App');
    config()->set('backup-restore.code_minutes', 10);
    config()->set('backup-restore.code_attempts', 5);
    Cache::flush();
});

it('schickt einen sechsstelligen Code an die Person', function () {
    $user = User::factory()->create();

    $code = codeHolen($user);

    expect($code)->toMatch('/^\d{6}$/');
    $mail = new BackupRestoreCodeMail($user, $code, 10);
    $mail->assertSeeInHtml($code)->assertSeeInHtml('10 Minuten');
});

it('laesst mit Code, Passwort und App-Namen durch und verbraucht den Code', function () {
    $user = User::factory()->create(['password' => 'geheim123']);
    $code = codeHolen($user);
    $b = app(BackupRestoreConfirmation::class);

    $b->pruefen($user, $code, 'geheim123', 'Coaching-App');

    expect(fehlerVon(fn () => $b->pruefen($user, $code, 'geheim123', 'Coaching-App')))->toHaveKey('code');
});

it('verlangt kein Passwort, wenn keines gesetzt ist', function () {
    $user = User::factory()->create(['password' => null]);
    $code = codeHolen($user);
    $b = app(BackupRestoreConfirmation::class);

    expect($b->passwortNoetig($user))->toBeFalse();
    $b->pruefen($user, $code, null, 'Coaching-App');
    expect(Cache::has('backup-restore:code:'.$user->id))->toBeFalse();
});

it('meldet jedes falsche Feld einzeln', function () {
    $user = User::factory()->create(['password' => 'geheim123']);
    codeHolen($user);

    $fehler = fehlerVon(fn () => app(BackupRestoreConfirmation::class)->pruefen($user, '000000', 'falsch', 'Andere App'));

    expect($fehler)->toHaveKeys(['code', 'passwort', 'app_name']);
});

it('sperrt den Code nach zu vielen Fehlversuchen', function () {
    $user = User::factory()->create(['password' => null]);
    $code = codeHolen($user);
    $b = app(BackupRestoreConfirmation::class);
    $falsch = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $i) {
        expect(fehlerVon(fn () => $b->pruefen($user, $falsch, null, 'Coaching-App'))['code'][0])->toBe('Der Code stimmt nicht.');
    }
    expect(fehlerVon(fn () => $b->pruefen($user, $falsch, null, 'Coaching-App'))['code'][0])->toContain('Zu viele Fehlversuche');
    // auch der richtige Code geht jetzt nicht mehr
    expect(fehlerVon(fn () => $b->pruefen($user, $code, null, 'Coaching-App'))['code'][0])->toContain('abgelaufen');
});

it('laesst den Code nach der Frist verfallen', function () {
    $user = User::factory()->create(['password' => null]);
    $code = codeHolen($user);

    $this->travel(11)->minutes();

    expect(fehlerVon(fn () => app(BackupRestoreConfirmation::class)->pruefen($user, $code, null, 'Coaching-App'))['code'][0])->toContain('abgelaufen');
});

it('haelt Codes je Person getrennt', function () {
    $a = User::factory()->create(['password' => null]);
    $b = User::factory()->create(['password' => null]);
    $codeA = codeHolen($a);
    codeHolen($b);

    expect(fehlerVon(fn () => app(BackupRestoreConfirmation::class)->pruefen($b, $codeA, null, 'Coaching-App')))->toHaveKey('code');
});
