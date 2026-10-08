<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = config('app.seed_admin.email');
        $adminPassword = config('app.seed_admin.password');

        if ((bool) $adminEmail !== (bool) $adminPassword) {
            throw new \InvalidArgumentException(
                'Configurez ensemble SGP_ADMIN_EMAIL et SGP_ADMIN_PASSWORD pour créer le compte administrateur.'
            );
        }

        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrNew(['email' => $adminEmail]);
            $admin->forceFill([
                'name' => config('app.seed_admin.name'),
                'password' => $adminPassword,
                'role' => 'admin',
                'statut' => 'actif',
            ])->save();
        }

        Fonction::updateOrCreate(
            ['libelle' => 'Chef de projet'],
            ['description' => 'Pilote le projet']
        );

        Fonction::updateOrCreate(
            ['libelle' => 'Membre'],
            ['description' => 'Participe au projet']
        );
    }
}
