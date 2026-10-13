<?php

namespace Tests\Feature;

use App\Models\Fonction;
use App\Models\Personnel;
use App\Models\Projet;
use App\Models\Tache;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Les tests API nécessitent l’extension PHP pdo_sqlite.');
        }

        parent::setUp();
    }

    public function test_task_routes_bind_the_task_and_status_changes_keep_the_due_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($admin);
        $projet = $this->createProjet();
        $tache = $this->createTache($projet, $personnel, '2030-05-20');

        Sanctum::actingAs($admin);

        $this->getJson('/api/taches/'.$tache->id)
            ->assertOk()
            ->assertJsonPath('id', $tache->id);

        $this->patchJson('/api/taches/'.$tache->id.'/status', ['statut' => 'terminé'])
            ->assertOk()
            ->assertJsonPath('statut', 'terminé');

        $this->assertSame('2030-05-20', $tache->fresh()->date_echeance->toDateString());
    }

    public function test_overdue_tasks_and_project_statistics_use_the_statut_column(): void
    {
        $user = User::factory()->create(['role' => 'user', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($user);
        $projet = $this->createProjet();
        $this->createTache($projet, $personnel, now()->subDay()->toDateString());

        Sanctum::actingAs($user);

        $this->getJson('/api/mes-taches?en_retard=1')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'en cours');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'statut' => 'actif']));

        $this->getJson('/api/projets/'.$projet->id.'/stats')
            ->assertOk()
            ->assertJsonPath('total_taches', 1)
            ->assertJsonPath('en_cours', 1)
            ->assertJsonPath('en_retard', 1);
    }

    public function test_project_patch_accepts_only_changed_fields_and_optional_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $projet = $this->createProjet();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/projets/'.$projet->id, ['titre' => 'Titre modifié'])
            ->assertOk()
            ->assertJsonPath('titre', 'Titre modifié');
    }

    public function test_tasks_cannot_be_added_to_a_completed_project_until_it_is_reopened(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($admin);
        $projet = $this->createProjet();
        $fonction = Fonction::create(['libelle' => 'Chef de projet']);
        $projet->membres()->attach($personnel->id, ['fonction_id' => $fonction->id]);

        Sanctum::actingAs($admin);

        $projet->update(['statut' => 'terminé']);
        $data = [
            'titre' => 'Nouvelle tâche',
            'description' => 'Description de test',
            'date_debut' => '2026-01-01',
            'date_echeance' => '2026-02-01',
            'responsable_id' => $personnel->id,
        ];

        $this->postJson('/api/projets/'.$projet->id.'/taches', $data)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ce projet est terminé : passez son statut à « En cours » pour pouvoir y ajouter des tâches.');

        $projet->update(['statut' => 'en cours']);

        $this->postJson('/api/projets/'.$projet->id.'/taches', $data)
            ->assertCreated()
            ->assertJsonPath('titre', 'Nouvelle tâche');
    }

    public function test_duplicate_personnel_names_are_allowed_but_oversized_full_names_are_rejected(): void
    {
        User::factory()->create(['name' => 'Alex Martin']);
        User::factory()->create(['name' => 'Alex Martin']);

        $this->assertDatabaseCount('users', 2);

        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/personnels', [
            'nom' => str_repeat('N', 100),
            'prenom' => str_repeat('P', 100),
            'poste' => 'Membre',
            'email' => 'personnel@example.test',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('nom');
    }

    public function test_inactive_accounts_cannot_use_authenticated_api_routes(): void
    {
        $user = User::factory()->create(['statut' => 'inactif']);

        Sanctum::actingAs($user);

        $this->getJson('/api/me')->assertForbidden();
    }

    public function test_database_seeder_can_be_run_repeatedly_with_configured_admin_credentials(): void
    {
        config([
            'app.seed_admin.name' => 'Admin de test',
            'app.seed_admin.email' => 'admin@example.test',
            'app.seed_admin.password' => 'un-secret-de-test',
        ]);

        $seeder = new DatabaseSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('fonctions', 2);
        $this->assertSame('admin', User::firstOrFail()->role);
        $this->assertTrue(password_verify('un-secret-de-test', User::firstOrFail()->password));
    }

    private function createPersonnel(User $user): Personnel
    {
        return Personnel::create([
            'nom' => 'Martin',
            'prenom' => 'Alex',
            'poste' => 'Membre',
            'user_id' => $user->id,
        ]);
    }

    private function createProjet(): Projet
    {
        return Projet::create([
            'titre' => 'Projet de test',
            'date_debut' => '2026-01-01',
            'date_fin' => '2030-12-31',
        ]);
    }

    private function createTache(Projet $projet, Personnel $personnel, string $dateEcheance): Tache
    {
        return Tache::create([
            'projet_id' => $projet->id,
            'responsable_id' => $personnel->id,
            'titre' => 'Tâche de test',
            'description' => 'Description de test',
            'date_debut' => '2026-01-01',
            'date_echeance' => $dateEcheance,
            'statut' => 'en cours',
            'priorite' => 'moyenne',
        ]);
    }
}
