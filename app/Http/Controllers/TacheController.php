<?php

namespace App\Http\Controllers;

use App\Http\Requests\TacheRequest;
use App\Models\Projet;
use App\Models\Tache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TacheController extends Controller
{
    public function index(Projet $projet)
    {
        Gate::authorize('voir', $projet);

        return $projet->taches()->with('responsable')->latest()->paginate(20);
    }

public function store(TacheRequest $request, Projet $projet)
{
    abort_if($projet->statut !== 'en cours', 422, "Ce projet est clos : impossible d'ajouter une tâche.");

    $tache = $projet->taches()->create($request->validated());

    return response()->json($tache->load('responsable'), 201);
}

    public function show(Tache $tache)
    {
        Gate::authorize('voir', $tache->projet);

        return $tache->load('responsable');
    }

    public function update(TacheRequest $request, Tache $tache)
    {
        $tache->update($request->validated());

        return $tache->load('responsable');
    }

    public function destroy(Tache $tache)
    {
        Gate::authorize('gerer', $tache->projet);
        $tache->delete();

        return response()->json(['message' => 'Tâche supprimée.']);
    }

    public function changerStatut(Request $request, Tache $tache)
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(['en cours', 'terminé', 'annulé'])],
        ]);

        $user = $request->user();
        $estResponsable = $tache->responsable->user_id === $user->id;

        abort_unless($estResponsable || $user->can('gerer', $tache->projet), 403);

        $tache->update(['statut' => $data['statut']]);

        return $tache;
    }

    public function mesTaches(Request $request)
    {
        $personnel = $request->user()->personnel;

        abort_unless($personnel, 404, 'Aucune fiche personnel associée à ce compte.');

        $query = $personnel->taches()
            ->with('projet:id,titre')
            ->orderBy('date_echeance');

        if ($request->boolean('en_retard')) {
            $query->where('statut', 'en cours')
                ->whereDate('date_echeance', '<', now()->toDateString());
        }

        return $query->paginate(20);
    }
}
