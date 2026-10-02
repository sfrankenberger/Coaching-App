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

    /**
     * In der Queue: Mandant setzen, bevor ein Kanal etwas liest. Der Mandant bleibt fuer den Rest des Jobs gesetzt,
     * weil die Mail erst nach toMail() gerendert wird (Rahmen mit Logo, Fusszeile, Farben braucht ihn dann noch).
     */
    public function withTenant(callable $fn): mixed
    {
        $current = app(CurrentTenant::class);
        if ($current->check() || ! $this->tenantId) {
            return $fn($current->get());
        }
        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            $current->set($tenant);
        }

        return $fn($tenant);
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
            if ($this->nachricht->anhang) {
                $mail->attachData($this->nachricht->anhang['inhalt'], $this->nachricht->anhang['name'], ['mime' => $this->nachricht->anhang['typ'] ?? 'application/octet-stream']);
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
