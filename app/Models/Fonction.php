<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fonction extends Model
{
    // Libellé de la fonction qui donne les droits de gestion d'un projet
    public const CHEF_DE_PROJET = 'Chef de projet';
    public const MEMBRE = 'Membre';

    protected $fillable = ['libelle', 'description'];
}
