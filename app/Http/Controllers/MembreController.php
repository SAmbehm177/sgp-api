<?php

namespace App\Http\Controllers;

use App\Models\Fonction;
use App\Models\Personnel;
use App\Models\Projet;
use App\Models\Tache;
use App\Notifications\MembreAjoute;
use App\Notifications\MembreRetire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MembreController extends Controller
{
    public function store(Request $request, Projet $projet)
    {
        Gate::authorize('gerer', $projet);

        $data = $request->validate([
            'personnel_id' => ['required', 'exists:personnels,id'],
            'fonction_id' => ['required', 'exists:fonctions,id'],
        ]);

        $fonction = Fonction::findOrFail($data['fonction_id']);

        // Vérification + écriture dans une même transaction, avec un verrou sur la ligne du projet :
        // deux ajouts simultanés ne peuvent plus créer deux chefs de projet.
        $erreur = DB::transaction(function () use ($projet, $data, $fonction): ?string {
            Projet::whereKey($projet->id)->lockForUpdate()->first();

            $existant = $projet->membres()->where('personnels.id', $data['personnel_id'])->first();

            if ($existant && $existant->pivot->date_retrait === null) {
                return 'Ce personnel est déjà membre du projet.';
            }

            // Un projet n'a qu'un seul chef de projet actif
            if ($fonction->libelle === Fonction::CHEF_DE_PROJET && $projet->chefs()->exists()) {
                return 'Ce projet a déjà un chef de projet : retirez-le avant d\'en désigner un autre.';
            }

            if ($existant) {
                $projet->membres()->updateExistingPivot($data['personnel_id'], [
                    'fonction_id' => $data['fonction_id'],
                    'date_retrait' => null,
                ]);
            } else {
                $projet->membres()->attach($data['personnel_id'], ['fonction_id' => $data['fonction_id']]);
            }

            return null;
        });

        if ($erreur !== null) {
            return response()->json(['message' => $erreur], 422);
        }

        // Prévient la personne ajoutée (sauf si c'est elle-même qui agit)
        $personnel = Personnel::with('user')->find($data['personnel_id']);

        if ($personnel?->user && $personnel->user_id !== Auth::id()) {
            $personnel->user->notify(new MembreAjoute($projet, $fonction->libelle));
        }

        return response()->json($projet->load('membresActifs'), 201);
    }

    public function retirer(Projet $projet, Personnel $personnel)
    {
        Gate::authorize('gerer', $projet);

        $actif = $projet->membresActifs()->where('personnels.id', $personnel->id)->exists();

        if (! $actif) {
            return response()->json(['message' => "Ce personnel n'est pas membre actif du projet."], 404);
        }

        $estChef = $projet->chefs()->where('personnels.id', $personnel->id)->exists();

        if ($estChef && ! Auth::user()->estAdmin()) {
            return response()->json([
                'message' => 'Seul un administrateur peut retirer le chef de projet.',
            ], 422);
        }

        if ($estChef) {
            return response()->json([
                'message' => 'Transférez le rôle à un membre actif avant de retirer le chef de projet.',
            ], 422);
        }

        $tachesOuvertes = Tache::where('projet_id', $projet->id)
            ->where('responsable_id', $personnel->id)
            ->where('statut', 'en cours')
            ->exists();

        if ($tachesOuvertes) {
            return response()->json([
                'message' => 'Ce membre a encore des tâches en cours : réaffectez-les ou terminez-les avant de le retirer.',
            ], 422);
        }

        $projet->membres()->updateExistingPivot($personnel->id, ['date_retrait' => now()->toDateString()]);

        // Prévient la personne retirée (sauf si c'est elle-même qui agit)
        $personnel->loadMissing('user');

        if ($personnel->user && $personnel->user_id !== Auth::id()) {
            $personnel->user->notify(new MembreRetire($projet));
        }

        return response()->json(['message' => 'Membre retiré du projet.']);
    }

    public function modifierFonction(Request $request, Projet $projet, Personnel $personnel)
    {
        abort_unless(Auth::user()->estAdmin(), 403, 'Seul un administrateur peut modifier la fonction d’un membre.');

        $data = $request->validate([
            'fonction_id' => ['required', 'exists:fonctions,id'],
        ]);
        $fonction = Fonction::findOrFail($data['fonction_id']);

        $erreur = DB::transaction(function () use ($projet, $personnel, $fonction): ?string {
            Projet::whereKey($projet->id)->lockForUpdate()->firstOrFail();

            $membreActif = $projet->membresActifs()
                ->where('personnels.id', $personnel->id)
                ->exists();

            if (! $membreActif) {
                abort(404, "Ce personnel n'est pas membre actif du projet.");
            }

            if (
                $fonction->libelle === Fonction::CHEF_DE_PROJET
                && $projet->chefs()->where('personnels.id', '!=', $personnel->id)->exists()
            ) {
                return 'Ce projet a déjà un chef de projet.';
            }

            $projet->membres()->updateExistingPivot($personnel->id, [
                'fonction_id' => $fonction->id,
            ]);

            return null;
        });

        if ($erreur !== null) {
            return response()->json(['message' => $erreur], 422);
        }

        return response()->json(['message' => 'Fonction du membre modifiée.']);
    }

    public function transfererChef(Request $request, Projet $projet)
    {
        abort_unless(Auth::user()->estAdmin(), 403, 'Seul un administrateur peut transférer le rôle de chef de projet.');

        $data = $request->validate([
            'ancien_chef_id' => ['required', 'integer', 'different:nouveau_chef_id', 'exists:personnels,id'],
            'nouveau_chef_id' => ['required', 'integer', 'exists:personnels,id'],
        ]);

        $resultat = DB::transaction(function () use ($projet, $data): array {
            $projet = Projet::whereKey($projet->id)->lockForUpdate()->firstOrFail();
            $fonctionChef = Fonction::where('libelle', Fonction::CHEF_DE_PROJET)->first();

            if (! $fonctionChef) {
                return ['status' => 422, 'message' => 'La fonction « Chef de projet » est introuvable.'];
            }

            $ancienChefActif = $projet->chefs()
                ->where('personnels.id', $data['ancien_chef_id'])
                ->exists();

            if (! $ancienChefActif) {
                return ['status' => 422, 'message' => 'Le membre à retirer n’est pas le chef actif de ce projet.'];
            }

            $nouveauChefActif = $projet->membresActifs()
                ->where('personnels.id', $data['nouveau_chef_id'])
                ->exists();

            if (! $nouveauChefActif) {
                return ['status' => 422, 'message' => 'Le nouveau chef doit être un membre actif de ce projet.'];
            }

            $tachesOuvertes = Tache::where('projet_id', $projet->id)
                ->where('responsable_id', $data['ancien_chef_id'])
                ->where('statut', 'en cours')
                ->exists();

            if ($tachesOuvertes) {
                return [
                    'status' => 422,
                    'message' => 'Ce membre a encore des tâches en cours : réaffectez-les ou terminez-les avant de le retirer.',
                ];
            }

            $projet->membres()->updateExistingPivot($data['nouveau_chef_id'], [
                'fonction_id' => $fonctionChef->id,
            ]);
            $projet->membres()->updateExistingPivot($data['ancien_chef_id'], [
                'date_retrait' => now()->toDateString(),
            ]);

            return ['ancien_chef_id' => $data['ancien_chef_id']];
        });

        if (isset($resultat['status'])) {
            return response()->json(['message' => $resultat['message']], $resultat['status']);
        }

        $ancienChef = Personnel::with('user')->findOrFail($resultat['ancien_chef_id']);

        if ($ancienChef->user && $ancienChef->user_id !== Auth::id()) {
            $ancienChef->user->notify(new MembreRetire($projet));
        }

        return response()->json(['message' => 'Le rôle de chef a été transféré et l’ancien chef a été retiré du projet.']);
    }
}
