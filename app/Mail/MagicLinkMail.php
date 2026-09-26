<?php

namespace App\Mail;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
        public int $minuten,
    ) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            replyTo: ($reply = $tenant?->setting('mail.reply_to')) ? [new Address($reply)] : [],
            subject: 'Dein Link zum Anmelden',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.magic-link', with: [
            'user' => $this->user,
            'url' => $this->url,
            'minuten' => $this->minuten,
        ]);
    }
}
