<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projet extends Model
{
    protected $fillable = ['titre', 'description', 'date_debut', 'date_fin', 'statut'];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];
    }

    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Personnel::class)
            ->withPivot('fonction_id', 'date_retrait')
            ->withTimestamps();
    }

    public function membresActifs(): BelongsToMany
    {
        return $this->membres()->wherePivotNull('date_retrait');
    }

    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class);
    }

    public function chefs()
    {
        $ids = Fonction::where('libelle', 'Chef de projet')->pluck('id');

        return $this->membresActifs()->wherePivotIn('fonction_id', $ids);
    }

    public function statistiques(): array
    {
        $aujourdhui = now()->toDateString();

        $parStatut = $this->taches()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $total = (int) $parStatut->sum();
        $terminees = (int) ($parStatut['terminé'] ?? 0);
        $annulees = (int) ($parStatut['annulé'] ?? 0);
        $actives = $total - $annulees;

        $chargeParMembre = $this->taches()
            ->join('personnels', 'personnels.id', '=', 'taches.responsable_id')
            ->where('taches.statut', '!=', 'annulé')
            ->selectRaw(
                "personnels.id as personnel_id, personnels.prenom, personnels.nom,
             count(*) as total,
             sum(taches.statut = 'terminé') as terminees,
             sum(taches.statut = 'en cours') as en_cours,
             sum(taches.statut = 'en cours' and taches.date_echeance < ?) as en_retard",
                [$aujourdhui]
            )
            ->groupBy('personnels.id', 'personnels.prenom', 'personnels.nom')
            ->get();

        return [
            'total_taches' => $total,
            'en_cours' => (int) ($parStatut['en cours'] ?? 0),
            'terminees' => $terminees,
            'annulees' => $annulees,
            'en_retard' => $this->taches()
                ->where('statut', 'en cours')
                ->whereDate('date_echeance', '<', $aujourdhui)
                ->count(),
            'taux_avancement' => $actives > 0 ? round($terminees / $actives * 100, 1) : 0,
            'charge_par_membre' => $chargeParMembre,
                'par_priorite' => $this->taches()
                ->selectRaw('priorite, count(*) as total')
                ->groupBy('priorite')
                ->pluck('total', 'priorite'),
        ];
    }
}
