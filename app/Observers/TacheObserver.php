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
        $this->synchroniserStatutProjet($tache);
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

            $this->synchroniserStatutProjet($tache);
        }
    }

    public function deleted(Tache $tache): void
    {
        $this->synchroniserStatutProjet($tache);
    }

    private function notifierResponsable(Tache $tache): void
    {
        $personnel = Personnel::with('user')->find($tache->responsable_id);

        if ($personnel && $personnel->user_id !== Auth::id()) {
            $personnel->user->notify(new TacheAffectee($tache));
        }
    }

    /**
     * Le statut du projet suit celui de ses tâches :
     * « terminé » quand toutes les tâches non annulées sont terminées,
     * « en cours » dès qu'il en reste une ouverte.
     * Un projet annulé n'est jamais modifié automatiquement.
     */
    private function synchroniserStatutProjet(Tache $tache): void
    {
        $projet = $tache->projet;

        if (! $projet || $projet->statut === 'annulé') {
            return;
        }

        $actives = $projet->taches()->where('statut', '!=', 'annulé')->count();

        if ($actives === 0) {
            return;
        }

        $terminees = $projet->taches()->where('statut', 'terminé')->count();
        $statut = $terminees === $actives ? 'terminé' : 'en cours';

        if ($projet->statut !== $statut) {
            $projet->update(['statut' => $statut]);
        }
    }
}
