<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Observers\TacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([TacheObserver::class])]
class Tache extends Model

{
    protected $fillable = [
        'projet_id', 'responsable_id', 'titre', 'description',
        'date_debut', 'date_echeance', 'statut', 'priorite',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_echeance' => 'date',
        ];
    }

    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_id');
    }
}
