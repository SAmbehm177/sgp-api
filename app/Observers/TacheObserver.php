<?php

namespace App\Observers;

use App\Models\Personnel;
use App\Models\Tache;
use App\Notifications\TacheAffectee;
use App\Notifications\TacheStatutModifie;

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
                ->filter(fn ($chef) => $chef->user_id !== auth()->id())
                ->each(fn ($chef) => $chef->user->notify(new TacheStatutModifie($tache)));
        }
    }

    private function notifierResponsable(Tache $tache): void
    {
        Personnel::with('user')->find($tache->responsable_id)
            ?->user->notify(new TacheAffectee($tache));
    }
}
