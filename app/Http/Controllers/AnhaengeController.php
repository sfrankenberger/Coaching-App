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
        return response()->json(['karten' => $anhaenge->suche($request->user(), (string) $request->query('q', ''))]);
    }
}
