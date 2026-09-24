<?php

return [
    /*
    | Mandant, der greift, wenn keine Domain passt (Konsole, Tests, lokale Entwicklung).
    | In Produktion leer lassen: unbekannte Domains bekommen dann 404.
    */
    'fallback_slug' => env('TENANCY_FALLBACK', null),

    /*
    | Domains der Plattform selbst (Plattform-Verwaltung, spaeter Marketing-Seite).
    */
    'central_domains' => array_filter(explode(',', env('TENANCY_CENTRAL_DOMAINS', ''))),
];
