<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Personnel;
use App\Models\Projet;
use App\Models\Tache;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_complete_and_idempotent(): void
    {
        config(['app.seed_demo.password' => 'Demo123!']);

        $this->seed(DatabaseSeeder::class);

        $projets = Projet::whereHas('membres', fn ($query) => $query->where('matricule', 'like', 'DEMO-%'));
        $this->assertSame(18, Personnel::where('matricule', 'like', 'DEMO-%')->count());
        $this->assertSame(11, $projets->count());

        $projetIds = $projets->pluck('id');
        $this->assertSame(88, Tache::whereIn('projet_id', $projetIds)->count());
        $this->assertSame(44, Message::whereIn('projet_id', $projetIds)->count());
        $this->assertSame(5, Projet::findOrFail($projetIds->first())->membresActifs()->count());

        $projetEnProgression = Projet::where('titre', 'Suivi des équipements')->firstOrFail();
        $this->assertSame(2, $projetEnProgression->taches()->where('statut', 'terminé')->count());
        $this->assertSame(5, $projetEnProgression->taches()->where('statut', 'en cours')->count());
        $this->assertSame(
            0,
            $projetEnProgression->taches()
                ->where('statut', 'en cours')
                ->whereDate('date_echeance', '<', today())
                ->count()
        );

        foreach ([
            ['DEMO-0001', 'Ariel', 'Traore'],
            ['DEMO-0002', 'Camille', 'Yaba'],
            ['DEMO-0003', 'Yohana', 'Waga'],
            ['DEMO-0004', 'Melvine', 'Kouamé'],
        ] as [$matricule, $prenom, $nom]) {
            $personnel = Personnel::where('matricule', $matricule)->with('user')->firstOrFail();
            $this->assertSame($prenom, $personnel->prenom);
            $this->assertSame($nom, $personnel->nom);
            $this->assertSame("$prenom $nom", $personnel->user->name);
        }

        $this->assertTrue(password_verify(
            'Demo123!',
            Personnel::where('matricule', 'DEMO-0001')->firstOrFail()->user->password
        ));
        $this->assertTrue(Personnel::where('matricule', 'DEMO-0001')->firstOrFail()->user->must_change_password);

        Personnel::where('matricule', 'DEMO-0001')->firstOrFail()->user->update(['name' => 'Ancien nom']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(18, Personnel::where('matricule', 'like', 'DEMO-%')->count());
        $this->assertSame(11, Projet::whereHas('membres', fn ($query) => $query->where('matricule', 'like', 'DEMO-%'))->count());
        $this->assertSame(88, Tache::whereIn('projet_id', $projetIds)->count());
        $this->assertSame(44, Message::whereIn('projet_id', $projetIds)->count());
        $this->assertSame(
            'Ariel Traore',
            Personnel::where('matricule', 'DEMO-0001')->firstOrFail()->user->fresh()->name
        );
        $this->postJson('/api/login', [
            'email' => 'demo.membre01@example.test',
            'password' => 'Demo123!',
        ])->assertOk()->assertJsonPath('user.must_change_password', true);
    }
}
