<?php

namespace App\Mail;

use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\NewsletterVersand;
use App\Newsletter\Vorlage;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** Newsletter oder Serienmail an einen Kontakt: Vorlage mit Bild, Headline, Text, Knopf, Abmeldelink mit einem Klick. */
class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Newsletter $newsletter, public Kontakt $kontakt, public ?NewsletterVersand $versand = null, public bool $test = false) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            replyTo: ($reply = $tenant?->setting('mail.reply_to')) ? [new Address($reply)] : [],
            subject: ($this->test ? '[Test] ' : '').Vorlage::platzhalter($this->newsletter->betreff, $this->kontakt),
        );
    }

    public function headers(): Headers
    {
        if (! $this->kontakt->exists) {
            return new Headers;
        }
        $url = route('newsletter.abmelden', $this->kontakt->token);

        return new Headers(text: ['List-Unsubscribe' => '<'.$url.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.newsletter', with: ['n' => $this->newsletter, 'k' => $this->kontakt, 'v' => $this->versand, 'test' => $this->test]);
    }
}
