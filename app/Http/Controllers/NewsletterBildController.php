<?php

namespace App\Http\Controllers;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Bilder aus dem Newsletter-Baukasten (storage/app/private/tenants/{id}/newsletter). Oeffentlich, lange gecacht, Mailprogramme laden sie von hier. */
class NewsletterBildController extends Controller
{
    public function __invoke(CurrentTenant $current, string $datei): Response
    {
        abort_unless(preg_match('~^[a-z0-9._-]+$~i', $datei), 404);
        $pfad = 'tenants/'.$current->getOrFail()->id.'/newsletter/'.$datei;
        abort_unless(Storage::exists($pfad), 404);

        return Storage::response($pfad, null, ['Cache-Control' => 'public, max-age=2592000']);
    }
}
