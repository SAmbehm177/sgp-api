<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès admin est contrôlé par le middleware can:admin
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:191'],
            'prenom' => ['required', 'string', 'max:191'],
            'poste' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
