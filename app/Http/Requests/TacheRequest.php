<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projet = $this->route('projet') ?? $this->route('tache')->projet;

        return $this->user()->can('gerer', $projet);
    }

    public function rules(): array
    {
        $r = $this->isMethod('post') ? 'required' : 'sometimes';
        $projetId = $this->route('projet')?->id ?? $this->route('tache')->projet_id;

        return [
            'titre' => [$r, 'string', 'max:255'],
            'description' => [$r, 'string'],
            'date_debut' => [$r, 'date'],
            'date_echeance' => [$r, 'date', 'after_or_equal:date_debut'],
            'statut' => ['sometimes', Rule::in(['en cours', 'terminé', 'annulé'])],
            'priorite' => ['sometimes', Rule::in(['basse', 'moyenne', 'haute'])],
            'responsable_id' => [
                $r,
                Rule::exists('personnel_projet', 'personnel_id')
                    ->where('projet_id', $projetId)
                    ->whereNull('date_retrait'),
            ],
        ];
    }
}
