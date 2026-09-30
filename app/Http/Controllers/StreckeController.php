<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\User;
use App\Programs\Strecke;
use Illuminate\View\View;

/** Signierter Link aus den Strecken-Mails: keine weiteren Erinnerungen zu diesem Kurs. */
class StreckeController extends Controller
{
    public function stopp(Strecke $strecke, int $program, int $user): View
    {
        $p = Program::findOrFail($program);
        $u = User::findOrFail($user);
        $strecke->stopp($u, $p);

        return view('strecke-stopp', ['program' => $p]);
    }
}
