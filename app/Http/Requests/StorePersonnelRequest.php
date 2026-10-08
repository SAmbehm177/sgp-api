<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès admin est contrôlé par le middleware can:admin
    }

    public function rules(): array
    {
        $fullNameFitsUserColumn = function ($attribute, $value, $fail): void {
            $fullName = trim($this->input('prenom', '').' '.$value);

            if (Str::length($fullName) > 191) {
                $fail('La combinaison du prénom et du nom ne peut pas dépasser 191 caractères.');
            }
        };

        return [
            'nom' => ['required', 'string', 'max:191', $fullNameFitsUserColumn],
            'prenom' => ['required', 'string', 'max:191'],
            'poste' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
