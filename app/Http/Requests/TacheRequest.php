<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projet = $this->route('projet') ?? $this->route('tache')?->projet;

        return $projet !== null
            && $this->user() !== null
            && $this->user()->can('gerer', $projet);
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') || $this->isMethod('put')
            ? 'required'
            : 'sometimes';
        $tache = $this->route('tache');
        $projet = $this->route('projet') ?? $tache?->projet;
        $projetId = $projet?->id;

        $dansLeProjet = function ($value) use ($projet): bool {
            if (! $projet) {
                return true;
            }

            $date = strtotime($value);

            return $date >= strtotime($projet->date_debut->toDateString())
                && $date <= strtotime($projet->date_fin->toDateString());
        };

        $messageProjet = fn () => 'La date doit être comprise entre le début ('
            .$projet->date_debut->format('d/m/Y').') et la fin ('
            .$projet->date_fin->format('d/m/Y').') du projet.';

        $dateDebut = function ($attribute, $value, $fail) use ($tache, $dansLeProjet, $messageProjet): void {
            $dateEcheance = $this->input('date_echeance', $tache?->date_echeance?->toDateString());

            if ($dateEcheance && strtotime($value) > strtotime($dateEcheance)) {
                $fail("La date de début doit être antérieure ou égale à la date d'échéance.");

                return;
            }

            if (! $dansLeProjet($value)) {
                $fail('La date de début : '.$messageProjet());
            }
        };

        $dateEcheance = function ($attribute, $value, $fail) use ($tache, $dansLeProjet, $messageProjet): void {
            $dateDebut = $this->input('date_debut', $tache?->date_debut?->toDateString());

            if ($dateDebut && strtotime($value) < strtotime($dateDebut)) {
                $fail("La date d'échéance doit être postérieure ou égale à la date de début.");

                return;
            }

            if (! $dansLeProjet($value)) {
                $fail("La date d'échéance : ".$messageProjet());
            }
        };

        return [
            'titre' => [$required, 'string', 'max:150'],
            'description' => [$required, 'string'],
            'date_debut' => [$required, 'date', $dateDebut],
            'date_echeance' => [$required, 'date', $dateEcheance],
            'statut' => ['sometimes', Rule::in(['en cours', 'terminé', 'annulé'])],
            'priorite' => ['sometimes', Rule::in(['basse', 'moyenne', 'haute'])],
            'responsable_id' => [
                $required,
                Rule::exists('personnel_projet', 'personnel_id')
                    ->where('projet_id', $projetId)
                    ->whereNull('date_retrait'),
            ],
        ];
    }
}
