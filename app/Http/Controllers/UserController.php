<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function changerStatus(Request $request, User $user)
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(['actif', 'inactif'])],
        ]);

        if ($user->is($request->user())) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre statut.'], 422);
        }

        $user->forceFill(['statut' => $data['statut']])->save();

        if ($data['statut'] === 'inactif') {
            $user->tokens()->delete();
        }

        return response()->json($user);
    }
}
