<?php

namespace App\Ai\Werkzeuge;

use App\Models\Newsletter;
use App\Models\User;
use App\Newsletter\Versand;

class NewsletterSenden extends Werkzeug
{
    public function __construct(protected Versand $versand) {}

    public function name(): string
    {
        return 'newsletter_senden';
    }

    public function beschreibung(): string
    {
        return 'Newsletter als Test an Adressen schicken oder wirklich senden (in Wellen an alle passenden Kontakte, nicht rueckgaengig). Ohne test_an und ohne bestaetigt=true passiert nichts, es kommt nur die Empfaengerzahl zurueck.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str([
            'newsletter_id' => ['type' => 'integer'],
            'test_an' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'bis zu fuenf Adressen fuer einen Test'],
            'bestaetigt' => ['type' => 'boolean', 'description' => 'true = wirklich an alle senden'],
        ], ['newsletter_id']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $n = Newsletter::find((int) ($args['newsletter_id'] ?? 0));
        abort_unless($n, 404, 'Newsletter nicht gefunden.');
        $out = ['newsletter_id' => $n->id, 'betreff' => $n->betreff, 'status' => $n->status, 'empfaenger' => $n->empfaengerQuery()->count(), 'tags' => $n->tags];
        if (! empty($args['test_an'])) {
            $out['test_geschickt'] = $this->versand->test($n, (array) $args['test_an']);
        }
        if ($args['bestaetigt'] ?? false) {
            abort_unless($n->istEntwurf(), 409, 'Dieser Newsletter ist schon unterwegs oder gesendet.');
            $out['gestartet'] = $this->versand->starten($n);
            $out['status'] = $n->fresh()->status;
            $out['hinweis'] = 'Versand laeuft in Wellen von '.Versand::WELLE.' pro Minute.';
        } elseif (empty($args['test_an'])) {
            $out['hinweis'] = 'Nichts gesendet. Zum Senden bestaetigt=true, fuer einen Test test_an.';
        }

        return $out;
    }
}
