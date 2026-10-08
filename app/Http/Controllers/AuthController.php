<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['prenom'].' '.$data['nom'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->personnel()->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'poste' => $data['poste'],
            ]);

            return $user;
        });

        // refresh() : récupère role et statut, posés par les valeurs par défaut de la base
        $user->refresh()->load('personnel');

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (! $user->estActif()) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('personnel'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return $request->user()->load('personnel');
    }
}
