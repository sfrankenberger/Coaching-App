<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Programs\ArbeitsbuchPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Die eigenen Antworten eines Kurses oder Arbeitsbuchs als PDF zum Sichern. */
class ArbeitsbuchPdfController extends Controller
{
    public function __invoke(Request $request, Program $program, ArbeitsbuchPdf $pdf): Response
    {
        Gate::authorize('view', $program);
        $user = $request->user();

        return response($pdf->erzeugen($user, $program), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->dateiname($user, $program).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
