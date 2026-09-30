<?php

namespace App\Mail;

use App\Models\Kontakt;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Double-Opt-in: bitte bestaetige deine Anmeldung. */
class KontaktBestaetigenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kontakt $kontakt, public string $url) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            subject: 'Bitte bestätige deine Anmeldung: '.app(Branding::class)->appName(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.kontakt-bestaetigen', with: ['k' => $this->kontakt, 'url' => $this->url]);
    }
}
