<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PersonnelController extends Controller
{
    // GET /personnels?q=&sans_acces=1 : annuaire (admin et chefs de projet)
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sans_acces' => ['nullable', 'boolean'],
        ]);
        $recherche = trim($data['q'] ?? '');

        return Personnel::select('id', 'nom', 'prenom', 'matricule', 'poste', 'user_id')
            ->when($request->boolean('sans_acces'), fn ($query) => $query->whereNull('user_id'))
            ->when($recherche !== '', function ($query) use ($recherche) {
                $motif = '%'.addcslashes($recherche, '%_\\').'%';

                $query->where(function ($sous) use ($motif) {
                    $sous->whereRaw("CONCAT(prenom, ' ', nom) like ?", [$motif])
                        ->orWhereRaw("CONCAT(nom, ' ', prenom) like ?", [$motif])
                        ->orWhere('matricule', 'like', $motif);
                });
            })
            ->orderBy('nom')
            ->paginate(30)
            ->withQueryString();
    }

    // POST /personnels (admin) : crée la fiche, sans compte. L'accès est accordé ensuite (voir accorderAcces).
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:191'],
            'prenom' => ['required', 'string', 'max:191'],
            'matricule' => ['required', 'string', 'max:50', 'unique:personnels,matricule'],
            'poste' => ['required', 'string', 'max:191'],
        ]);

        // fresh() : la réponse contient aussi user_id (nul tant qu'aucun accès n'est accordé)
        return response()->json(Personnel::create($data)->fresh(), 201);
    }

    // PATCH /personnels/{personnel} (admin) : corrige la fiche ; le nom du compte suit s'il existe
    public function update(Request $request, Personnel $personnel)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:191'],
            'prenom' => ['sometimes', 'required', 'string', 'max:191'],
            'matricule' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('personnels', 'matricule')->ignore($personnel->id)],
            'poste' => ['sometimes', 'required', 'string', 'max:191'],
        ]);

        DB::transaction(function () use ($personnel, $data) {
            $personnel->update($data);

            if ($personnel->user && (isset($data['nom']) || isset($data['prenom']))) {
                $personnel->user->forceFill(['name' => $this->nomComplet($personnel)])->save();
            }
        });

        return $personnel->fresh();
    }

    // POST /personnels/{personnel}/acces (admin) : crée le compte du personnel (mot de passe provisoire fixé par l'admin)
    public function accorderAcces(Request $request, Personnel $personnel)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role' => ['nullable', Rule::in(['user', 'admin'])],
        ]);

        // Verrou sur la fiche : deux demandes simultanées ne peuvent pas créer deux comptes
        $erreur = DB::transaction(function () use ($personnel, $data): ?string {
            $fiche = Personnel::whereKey($personnel->id)->lockForUpdate()->first();

            if ($fiche->user_id !== null) {
                return 'Ce personnel a déjà un accès.';
            }

            $user = User::create([
                'name' => $this->nomComplet($fiche),
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Mot de passe provisoire : l'utilisateur devra le remplacer dès sa première connexion
            $user->forceFill([
                'role' => ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user',
                'must_change_password' => true,
            ])->save();

            $fiche->update(['user_id' => $user->id]);

            return null;
        });

        if ($erreur !== null) {
            return response()->json(['message' => $erreur], 422);
        }

        return response()->json($personnel->fresh()->load('user:id,name,email,role,statut'), 201);
    }

    // Le nom du compte est « prénom nom » (colonne users.name limitée à 191 caractères)
    private function nomComplet(Personnel $personnel): string
    {
        return Str::limit(trim($personnel->prenom.' '.$personnel->nom), 191, '');
    }
}
