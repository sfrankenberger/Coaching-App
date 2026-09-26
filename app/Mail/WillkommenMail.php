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

/** Erste Mail an eine neue Person: dein Zugang ist da, hier der Link. */
class WillkommenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url, public ?string $angebot = null) {}

    public function envelope(): Envelope
    {
        $tenant = app(CurrentTenant::class)->get();
        $from = $tenant?->setting('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, $tenant->setting('mail.from_name', $tenant->name)) : null,
            replyTo: ($reply = $tenant?->setting('mail.reply_to')) ? [new Address($reply)] : [],
            subject: 'Dein Zugang zu '.app(Branding::class)->appName(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.willkommen', with: ['user' => $this->user, 'url' => $this->url, 'angebot' => $this->angebot]);
    }
}
