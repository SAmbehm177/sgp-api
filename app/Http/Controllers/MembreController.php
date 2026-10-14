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

        $existant = $projet->membres()->where('personnels.id', $data['personnel_id'])->first();

        if ($existant && $existant->pivot->date_retrait === null) {
            return response()->json(['message' => 'Ce personnel est déjà membre du projet.'], 422);
        }

        if ($existant) {
            $projet->membres()->updateExistingPivot($data['personnel_id'], [
                'fonction_id' => $data['fonction_id'],
                'date_retrait' => null,
            ]);
        } else {
            $projet->membres()->attach($data['personnel_id'], ['fonction_id' => $data['fonction_id']]);
        }

        // Prévient la personne ajoutée (sauf si c'est elle-même qui agit)
        $personnel = Personnel::with('user')->find($data['personnel_id']);
        $fonction = Fonction::find($data['fonction_id']);

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

        if ($estChef && $projet->chefs()->count() <= 1) {
            return response()->json([
                'message' => 'Un projet doit conserver au moins un chef de projet.',
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
}
