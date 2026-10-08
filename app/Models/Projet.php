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
        return ['date_debut' => 'date', 'date_fin' => 'date'];
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
}
