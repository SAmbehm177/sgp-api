<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $recherche = trim($data['q'] ?? '');

        return Personnel::select('id', 'nom', 'prenom', 'poste')
            ->when($recherche !== '', function ($query) use ($recherche) {
                $motif = '%'.addcslashes($recherche, '%_\\').'%';

                $query->where(function ($sous) use ($motif) {
                    $sous->whereRaw("CONCAT(prenom, ' ', nom) like ?", [$motif])
                        ->orWhereRaw("CONCAT(nom, ' ', prenom) like ?", [$motif]);
                });
            })
            ->orderBy('nom')
            ->paginate(30)
            ->withQueryString();
    }
}
