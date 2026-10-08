<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function estActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function estChefDeProjet(): bool
    {
        if (! $this->personnel) {
            return false;
        }

        return DB::table('personnel_projet')
            ->join('fonctions', 'fonctions.id', '=', 'personnel_projet.fonction_id')
            ->where('personnel_projet.personnel_id', $this->personnel->id)
            ->where('fonctions.libelle', 'Chef de projet')
            ->whereNull('personnel_projet.date_retrait')
            ->exists();
    }
}
