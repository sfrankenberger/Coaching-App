<?php

namespace App\Mail;

use App\Models\Verkauf;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Nach einem Verkauf: Rechnung oder Quittung als PDF, Link zum Online-Bezahlen, Anmeldelink in die App. */
class RechnungMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Verkauf $verkauf, public ?string $url = null, public ?string $pdf = null) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');
        $offen = $this->verkauf->zahlungsart === 'rechnung';

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            replyTo: ($reply = $tenant?->setting('mail.reply_to')) ? [new Address($reply)] : [],
            subject: ($offen ? 'Deine Rechnung' : 'Deine Quittung').': '.$this->verkauf->title.' bei '.app(Branding::class)->coachName(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.rechnung', with: ['v' => $this->verkauf, 'user' => $this->verkauf->user, 'url' => $this->url, 'mitPdf' => $this->pdf !== null]);
    }

    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->pdf, 'Rechnung-'.($this->verkauf->rechnung_nr ?: $this->verkauf->id).'.pdf')->withMime('application/pdf')];
    }
}
