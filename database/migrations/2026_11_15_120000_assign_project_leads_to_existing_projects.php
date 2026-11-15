<?php

use App\Models\Fonction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projets = DB::table('projets')->pluck('id');
        if ($projets->isEmpty()) {
            return;
        }

        $now = now();
        $fonctionChefId = DB::table('fonctions')
            ->where('libelle', Fonction::CHEF_DE_PROJET)
            ->value('id');

        if (! $fonctionChefId) {
            $fonctionChefId = DB::table('fonctions')->insertGetId([
                'libelle' => Fonction::CHEF_DE_PROJET,
                'description' => 'Pilote le projet',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::transaction(function () use ($fonctionChefId, $projets): void {
            foreach ($projets as $projetId) {
                $aUnChef = DB::table('personnel_projet')
                    ->join('fonctions', 'fonctions.id', '=', 'personnel_projet.fonction_id')
                    ->where('personnel_projet.projet_id', $projetId)
                    ->whereNull('personnel_projet.date_retrait')
                    ->where('fonctions.libelle', Fonction::CHEF_DE_PROJET)
                    ->exists();

                if ($aUnChef) {
                    continue;
                }

                $premierMembre = DB::table('personnel_projet')
                    ->where('projet_id', $projetId)
                    ->whereNull('date_retrait')
                    ->orderBy('personnel_id')
                    ->value('personnel_id');

                if ($premierMembre) {
                    DB::table('personnel_projet')
                        ->where('projet_id', $projetId)
                        ->where('personnel_id', $premierMembre)
                        ->update([
                            'fonction_id' => $fonctionChefId,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    public function down(): void
    {
        // The promoted assignments cannot be safely distinguished from deliberate role changes.
    }
};
