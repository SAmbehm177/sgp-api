<?php

namespace App\Observers;

use App\Models\Personnel;
use App\Models\Tache;
use App\Notifications\TacheAffectee;
use App\Notifications\TacheStatutModifie;
use Illuminate\Support\Facades\Auth;

class TacheObserver
{
    public function created(Tache $tache): void
    {
        $this->notifierResponsable($tache);
    }

    public function updated(Tache $tache): void
    {
        if ($tache->wasChanged('responsable_id')) {
            $this->notifierResponsable($tache);
        }

        if ($tache->wasChanged('statut')) {
            $tache->projet->chefs()->with('user')->get()
                ->filter(fn ($chef) => $chef->user_id !== Auth::id())
                ->each(fn ($chef) => $chef->user->notify(new TacheStatutModifie($tache)));
        }
    }

    private function notifierResponsable(Tache $tache): void
    {
        $personnel = Personnel::with('user')->find($tache->responsable_id);

        if ($personnel && $personnel->user_id !== Auth::id()) {
            $personnel->user->notify(new TacheAffectee($tache));
        }
    }
}
