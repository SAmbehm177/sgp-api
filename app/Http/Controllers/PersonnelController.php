<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonnelRequest;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PersonnelController extends Controller
{
    public function index()
    {
        return Personnel::select('id', 'nom', 'prenom', 'poste')
            ->orderBy('nom')
            ->paginate(30);
    }

    public function store(StorePersonnelRequest $request)
    {
        $data = $request->validated();

        $personnel = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['prenom'].' '.$data['nom'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            return $user->personnel()->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'poste' => $data['poste'],
            ]);
        });

        return response()->json($personnel->load('user'), 201);
    }
}
