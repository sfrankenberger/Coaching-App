<?php

namespace App\Http\Controllers;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Logo und App-Icon des Mandanten, aus den Einstellungen hochgeladen (storage/app/private/tenants/{id}/branding). Oeffentlich, lange gecacht. */
class BrandingController extends Controller
{
    public function __invoke(CurrentTenant $current, string $datei): Response
    {
        abort_unless(preg_match('~^[a-z0-9._-]+$~i', $datei), 404);
        $pfad = 'tenants/'.$current->getOrFail()->id.'/branding/'.$datei;
        abort_unless(Storage::exists($pfad), 404);

        return Storage::response($pfad, null, ['Cache-Control' => 'public, max-age=604800']);
    }
}
