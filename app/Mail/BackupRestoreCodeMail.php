<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Bestaetigungscode fuer eine Wiederherstellung, geht nur an Plattform-Admins. */
class BackupRestoreCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $minuten,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dein Code für die Wiederherstellung');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.backup-restore-code', with: [
            'user' => $this->user,
            'code' => $this->code,
            'minuten' => $this->minuten,
            'appName' => config('app.name'),
        ]);
    }
}
