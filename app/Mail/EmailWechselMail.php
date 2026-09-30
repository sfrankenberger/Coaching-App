<?php

namespace App\Mail;

use App\Models\User;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Bestaetigung an die neue Adresse, bevor die Mailadresse einer Person wechselt. */
class EmailWechselMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $neu, public string $url) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            subject: 'Neue Mailadresse bestätigen: '.app(Branding::class)->appName(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.email-wechsel', with: ['user' => $this->user, 'neu' => $this->neu, 'url' => $this->url]);
    }
}
