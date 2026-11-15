<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjetRequest;
use App\Models\Fonction;
use App\Models\Personnel;
use App\Models\Projet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class ProjetController extends Controller
{
public function index(Request $request)
{
    $user = $request->user();
    $query = Projet::query()
        ->withCount([
            'taches as taches_actives_count' => fn ($q) => $q->where('statut', '!=', 'annulé'),
            'taches as taches_en_cours_count' => fn ($q) => $q->where('statut', 'en cours'),
            'taches as taches_terminees_count' => fn ($q) => $q->where('statut', 'terminé'),
            'taches as taches_annulees_count' => fn ($q) => $q->where('statut', 'annulé'),
            'taches as taches_en_retard_count' => fn ($q) => $q
                ->where('statut', 'en cours')
                ->whereDate('date_echeance', '<', today()),
            'membresActifs as membres_count',
        ])
        ->latest();

    if (! $user->estAdmin()) {
        $personnelId = $user->personnel?->id;

        $query
            ->whereHas('membresActifs', fn ($q) => $q->where('personnels.id', $personnelId))
            // joint uniquement SA propre affectation (sa fonction dans chaque projet)
            ->with(['membresActifs' => fn ($q) => $q->where('personnels.id', $personnelId)]);
    }

    $page = $query->paginate(15);

    // Noms des chefs de projet des projets de la page (une seule requête)
    $chefs = DB::table('personnel_projet')
        ->join('personnels', 'personnels.id', '=', 'personnel_projet.personnel_id')
        ->join('fonctions', 'fonctions.id', '=', 'personnel_projet.fonction_id')
        ->whereIn('personnel_projet.projet_id', $page->getCollection()->pluck('id'))
        ->where('fonctions.libelle', Fonction::CHEF_DE_PROJET)
        ->whereNull('personnel_projet.date_retrait')
        ->orderBy('personnels.nom')
        ->get(['personnel_projet.projet_id', 'personnels.prenom', 'personnels.nom'])
        ->groupBy('projet_id');

    $page->getCollection()->transform(function (Projet $projet) use ($chefs) {
        $projet->setAttribute(
            'chefs_noms',
            ($chefs[$projet->id] ?? collect())->map(fn ($c) => $c->prenom.' '.$c->nom)->values()
        );

        return $projet;
    });

    return $page;
}

    public function store(ProjetRequest $request)
    {
        $data = $request->validated();
        $chefId = $data['chef_id'];
        unset($data['chef_id']);

        $fonctionChef = Fonction::where('libelle', Fonction::CHEF_DE_PROJET)->first();
        if (! $fonctionChef) {
            return response()->json(['message' => 'La fonction « Chef de projet » est introuvable.'], 422);
        }

        $projet = DB::transaction(function () use ($data, $chefId, $fonctionChef) {
            $chef = Personnel::whereKey($chefId)
                ->whereHas('user', fn ($query) => $query->where('statut', 'actif'))
                ->lockForUpdate()
                ->first();
            if (! $chef) {
                return null;
            }

            $projet = Projet::create($data);
            $projet->membres()->attach($chef->id, ['fonction_id' => $fonctionChef->id]);

            return $projet;
        });

        if (! $projet) {
            return response()->json(['message' => 'Le chef de projet doit avoir un compte personnel.'], 422);
        }

        return response()->json($projet->load('membresActifs'), 201);
    }

    public function show(Projet $projet)
    {
        Gate::authorize('voir', $projet);

        return $projet->load('membresActifs');
    }

    public function update(ProjetRequest $request, Projet $projet)
    {
        $projet->update($request->validated());

        return $projet;
    }

    public function destroy(Projet $projet)
    {
        Gate::authorize('admin');
        $projet->delete();

        return response()->json(['message' => 'Projet supprimé.']);
    }
public function stats(Projet $projet)
{
    Gate::authorize('voir', $projet);

    $stats = $projet->statistiques();

    // Charge par collaborateur et répartition par priorité : réservées à l'admin et aux chefs de projet
    if (! Gate::allows('gerer', $projet)) {
        unset($stats['charge_par_membre'], $stats['par_priorite']);
    }

    return $stats;
}
}
