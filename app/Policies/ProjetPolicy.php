<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjetPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->estActif()) {
            return false;
        }

        return $user->estAdmin() ? true : null;
    }

    public function voir(User $user, Projet $projet): bool
    {
        return $this->affectation($user, $projet) !== null;
    }

    public function gerer(User $user, Projet $projet): bool
    {
        return $this->affectation($user, $projet)?->libelle === 'Chef de projet';
    }

    private function affectation(User $user, Projet $projet): ?object
    {
        if (! $user->personnel) {
            return null;
        }

        return DB::table('personnel_projet')
            ->join('fonctions', 'fonctions.id', '=', 'personnel_projet.fonction_id')
            ->where('personnel_projet.projet_id', $projet->id)
            ->where('personnel_projet.personnel_id', $user->personnel->id)
            ->whereNull('personnel_projet.date_retrait')
            ->select('fonctions.libelle')
            ->first();
    }
}
