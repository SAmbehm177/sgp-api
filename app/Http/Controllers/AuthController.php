<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
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

    // PUT /password : l'utilisateur change son propre mot de passe (par exemple le mot de passe provisoire donné par l'admin)
    public function changerMotDePasse(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        // Les autres sessions (autres appareils) sont déconnectées ; la session courante reste ouverte
        $courant = $user->currentAccessToken();
        $user->tokens()
            ->when($courant instanceof PersonalAccessToken, fn ($requete) => $requete->where('id', '!=', $courant->id))
            ->delete();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    public function me(Request $request)
    {
        return $request->user()->load('personnel');
    }
}
