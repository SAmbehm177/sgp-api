<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjetRequest;
use App\Models\Projet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Projet::query()->latest();

        if (! $user->estAdmin()) {
            $personnelId = $user->personnel?->id;
            $query->whereHas('membresActifs', fn ($q) => $q->where('personnels.id', $personnelId));
        }

        return $query->paginate(15);
    }

    public function store(ProjetRequest $request)
    {
        return response()->json(Projet::create($request->validated()), 201);
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

    return $projet->statistiques();
}
}
