<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') || $this->isMethod('put')
            ? 'required'
            : 'sometimes';
        $projet = $this->route('projet');

        $dateDebut = function ($attribute, $value, $fail) use ($projet): void {
            $dateFin = $this->input('date_fin', $projet?->date_fin?->toDateString());

            if ($dateFin && strtotime($value) > strtotime($dateFin)) {
                $fail('La date de début doit être antérieure ou égale à la date de fin.');
            }
        };

        $dateFin = function ($attribute, $value, $fail) use ($projet): void {
            $dateDebut = $this->input('date_debut', $projet?->date_debut?->toDateString());

            if ($dateDebut && strtotime($value) < strtotime($dateDebut)) {
                $fail('La date de fin doit être postérieure ou égale à la date de début.');
            }
        };

        return [
            'titre' => [$required, 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date_debut' => [$required, 'date', $dateDebut],
            'date_fin' => [$required, 'date', $dateFin],
            'statut' => ['sometimes', Rule::in(['en cours', 'terminé', 'annulé'])],
        ];
    }
}
