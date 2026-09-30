<?php

namespace App\Mail;

use App\Models\Kontakt;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Abmeldung ohne Token (alte Links, Formular): schickt den persoenlichen Abmeldelink. */
class AbmeldeLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kontakt $kontakt, public string $url) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            subject: 'Dein Abmeldelink',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.abmelde-link', with: ['k' => $this->kontakt, 'url' => $this->url]);
    }
}
