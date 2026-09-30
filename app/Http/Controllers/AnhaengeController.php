<?php

namespace App\Http\Controllers;

use App\Support\Anhaenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Suche fuer das Anhaengen: Aufgaben, Notizen, Reflexionen, Termine, Aufzeichnungen, Material, Lektionen. */
class AnhaengeController extends Controller
{
    public function suche(Request $request, Anhaenge $anhaenge): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        // Ohne Suchwort: die letzten je Art, wie beim Oeffnen der Auswahl
        return response()->json(['karten' => $q === '' ? $anhaenge->auswahl($request->user()) : $anhaenge->suche($request->user(), $q)]);
    }
}
