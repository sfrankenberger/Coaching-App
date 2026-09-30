<?php

namespace App\Support\Backup;

use App\Mail\BackupRestoreCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Dreifache Bestaetigung vor einer Wiederherstellung: Code per Mail (kurz gueltig,
 * wenige Versuche), Passwort falls eines gesetzt ist, und der App-Name abgetippt.
 */
class BackupRestoreConfirmation
{
    public function codeSenden(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minuten = max(1, (int) config('backup-restore.code_minutes'));

        Cache::put($this->schluessel($user), ['hash' => Hash::make($code), 'versuche' => 0], now()->addMinutes($minuten));

        Mail::to($user->email)->send(new BackupRestoreCodeMail($user, $code, $minuten));
    }

    /** Name, der abgetippt werden muss. */
    public function erwarteterName(): string
    {
        return (string) config('app.name');
    }

    public function passwortNoetig(User $user): bool
    {
        return ! empty($user->password);
    }

    /**
     * Prueft alle drei Angaben. Wirft eine ValidationException mit Feldnamen, damit das
     * Formular die Fehler am richtigen Feld zeigt. Bei Erfolg ist der Code verbraucht.
     */
    public function pruefen(User $user, string $code, ?string $passwort, string $appName): void
    {
        $fehler = [];

        if (trim($appName) !== $this->erwarteterName()) {
            $fehler['app_name'] = 'Der Name stimmt nicht. Bitte genau so schreiben: '.$this->erwarteterName();
        }

        if ($this->passwortNoetig($user) && ! Hash::check((string) $passwort, $user->password)) {
            $fehler['passwort'] = 'Das Passwort stimmt nicht.';
        }

        $schluessel = $this->schluessel($user);
        $eintrag = Cache::get($schluessel);
        if (! is_array($eintrag)) {
            $fehler['code'] = 'Der Code ist abgelaufen. Bitte das Fenster schliessen und neu öffnen, dann kommt ein neuer Code.';
        } elseif (! Hash::check(trim($code), $eintrag['hash'])) {
            $eintrag['versuche']++;
            if ($eintrag['versuche'] >= max(1, (int) config('backup-restore.code_attempts'))) {
                Cache::forget($schluessel);
                $fehler['code'] = 'Zu viele Fehlversuche. Bitte das Fenster schliessen und neu öffnen, dann kommt ein neuer Code.';
            } else {
                Cache::put($schluessel, $eintrag, now()->addMinutes(max(1, (int) config('backup-restore.code_minutes'))));
                $fehler['code'] = 'Der Code stimmt nicht.';
            }
        }

        if ($fehler) {
            throw ValidationException::withMessages($fehler);
        }

        Cache::forget($schluessel);
    }

    protected function schluessel(User $user): string
    {
        return 'backup-restore:code:'.$user->id;
    }
}
