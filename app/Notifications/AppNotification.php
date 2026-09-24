<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eine Benachrichtigung ueber die vom Notifier gewaehlten Kanaele.
 * Traegt die tenant_id, damit sie in der Queue im richtigen Mandanten laeuft.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Nachricht $nachricht, public array $channels, public ?int $tenantId) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** In der Queue: Mandant setzen, bevor ein Kanal etwas liest. */
    public function withTenant(callable $fn): mixed
    {
        $current = app(CurrentTenant::class);
        if ($current->check() || ! $this->tenantId) {
            return $fn($current->get());
        }
        $tenant = Tenant::find($this->tenantId);

        return $tenant ? $current->run($tenant, fn () => $fn($tenant)) : $fn(null);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->withTenant(function (?Tenant $tenant) use ($notifiable) {
            $branding = app(Branding::class);
            $from = $tenant?->setting('mail.from_address');

            $mail = (new MailMessage)
                ->subject($this->nachricht->mailBetreff ?: $this->nachricht->titel)
                ->view('mail.nachricht', [
                    'user' => $notifiable,
                    'nachricht' => $this->nachricht,
                    'appName' => $branding->appName(),
                    'branding' => $branding,
                ]);
            if ($from) {
                $mail->from($from, $tenant->setting('mail.from_name', $tenant->name));
            }

            return $mail;
        });
    }

    public function toWebPush(object $notifiable): array
    {
        return $this->nachricht->toArray();
    }

    public function toTelegram(object $notifiable): array
    {
        return $this->nachricht->toArray();
    }
}
