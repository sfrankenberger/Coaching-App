<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use App\Support\Zeit;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Bestaetigung fuer Gaeste nach der Buchung, mit Anmeldelink in die App (sieben Tage gueltig). */
class GastBuchungMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Booking $booking, public string $url, public bool $neu = true) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name') ?: app(Branding::class)->coachName()) : null,
            subject: 'Dein Termin ist gebucht: '.Zeit::wann($this->booking->starts_at),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.gast-buchung', with: ['user' => $this->user, 'booking' => $this->booking, 'url' => $this->url, 'neu' => $this->neu]);
    }
}
