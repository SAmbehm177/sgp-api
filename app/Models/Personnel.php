<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personnel extends Model
{
    protected $fillable = ['nom', 'prenom', 'poste', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projets(): BelongsToMany
    {
        return $this->belongsToMany(Projet::class)
            ->withPivot('fonction_id', 'date_retrait')
            ->withTimestamps();
    }

    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class, 'responsable_id');
    }
}
