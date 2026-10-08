<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\User;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::forceCreate([
            'name' => 'Administrateur',
            'email' => 'admin@exemple.sgp',
            'password' => 'MotDP§123',
            'role' => 'admin',
            'statut' => 'actif',
        ]);

        Fonction::insert([
            ['libelle' => 'Chef de projet', 'description' => 'Pilote le projet', 'created_at' => now(), 'updated_at' => now()],
            ['libelle' => 'Membre', 'description' => 'Participe au projet', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
