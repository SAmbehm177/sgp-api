<?php

namespace Tests\Feature;

use App\Models\Fonction;
use App\Models\Message;
use App\Models\Personnel;
use App\Models\Projet;
use App\Models\Tache;
use App\Models\User;
use App\Notifications\MembreRetire;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_project_creation_requires_and_assigns_an_active_project_lead(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $chef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);

        Sanctum::actingAs($admin);

        $data = [
            'titre' => 'Projet avec chef',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'chef_id' => $chef->id,
        ];

        $this->postJson('/api/projets', $data)
            ->assertCreated()
            ->assertJsonPath('membres_actifs.0.id', $chef->id)
            ->assertJsonPath('membres_actifs.0.pivot.fonction_id', Fonction::where('libelle', Fonction::CHEF_DE_PROJET)->value('id'));

        $this->postJson('/api/projets', array_diff_key($data, ['chef_id' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chef_id');
    }

    public function test_migration_promotes_the_first_active_member_on_projects_without_a_lead(): void
    {
        $personnel = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionMembre = Fonction::create(['libelle' => Fonction::MEMBRE]);
        $projet->membres()->attach($personnel->id, ['fonction_id' => $fonctionMembre->id]);

        $migration = require database_path('migrations/2026_11_15_120000_assign_project_leads_to_existing_projects.php');
        $migration->up();

        $fonctionChef = Fonction::where('libelle', Fonction::CHEF_DE_PROJET)->firstOrFail();
        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $personnel->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
    }

    public function test_task_patch_accepts_only_changed_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($admin);
        $projet = $this->createProjet();
        $tache = $this->createTache($projet, $personnel, '2030-05-20');

        Sanctum::actingAs($admin);

        $this->patchJson('/api/taches/'.$tache->id, ['titre' => 'Titre modifié'])
            ->assertOk()
            ->assertJsonPath('titre', 'Titre modifié')
            ->assertJsonPath('description', $tache->description);

        $this->assertSame('2030-05-20', $tache->fresh()->date_echeance->toDateString());
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
            ->assertJsonPath('message', 'Ce projet est terminé : rouvrez-le avant d’y ajouter des tâches.');

        $projet->update(['statut' => 'en cours']);

        $this->postJson('/api/projets/'.$projet->id.'/taches', $data)
            ->assertCreated()
            ->assertJsonPath('titre', 'Nouvelle tâche');
    }

    public function test_task_cannot_be_reopened_in_a_cancelled_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($admin);
        $projet = $this->createProjet();
        $tache = $this->createTache($projet, $personnel, '2030-05-20');
        Sanctum::actingAs($admin);

        $projet->update(['statut' => 'annulé']);
        $this->patchJson('/api/taches/'.$tache->id.'/status', ['statut' => 'terminé'])
            ->assertOk()
            ->assertJsonPath('statut', 'terminé');

        $this->assertSame('annulé', $projet->fresh()->statut);
        $this->patchJson('/api/taches/'.$tache->id.'/status', ['statut' => 'en cours'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Impossible de rouvrir une tâche dans un projet annulé.');

        $this->assertSame('terminé', $tache->fresh()->statut);
    }

    public function test_admin_can_atomically_transfer_project_lead_and_remove_previous_lead(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $ancienChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $nouveauChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $fonctionMembre = Fonction::create(['libelle' => 'Membre']);
        $projet->membres()->attach($ancienChef->id, ['fonction_id' => $fonctionChef->id]);
        $projet->membres()->attach($nouveauChef->id, ['fonction_id' => $fonctionMembre->id]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/projets/'.$projet->id.'/transfert-chef', [
            'ancien_chef_id' => $ancienChef->id,
            'nouveau_chef_id' => $nouveauChef->id,
        ])->assertOk();

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $ancienChef->id,
            'date_retrait' => now()->toDateString(),
        ]);
        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $nouveauChef->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
        Notification::assertSentTo($ancienChef->user, MembreRetire::class);
    }

    public function test_admin_can_change_an_active_members_function_without_removing_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionMembre = Fonction::create(['libelle' => 'Membre']);
        $fonctionExpert = Fonction::create(['libelle' => 'Expert technique']);
        $projet->membres()->attach($personnel->id, ['fonction_id' => $fonctionMembre->id]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/projets/'.$projet->id.'/membres/'.$personnel->id, [
            'fonction_id' => $fonctionExpert->id,
        ])->assertOk()
            ->assertJsonPath('message', 'Fonction du membre modifiée.');

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $personnel->id,
            'fonction_id' => $fonctionExpert->id,
            'date_retrait' => null,
        ]);
    }

    public function test_non_admin_cannot_change_a_members_function(): void
    {
        $user = User::factory()->create(['role' => 'user', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($user);
        $autrePersonnel = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonction = Fonction::create(['libelle' => 'Membre']);
        $projet->membres()->attach($autrePersonnel->id, ['fonction_id' => $fonction->id]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/projets/'.$projet->id.'/membres/'.$autrePersonnel->id, [
            'fonction_id' => $fonction->id,
        ])->assertForbidden();
    }

    public function test_non_admin_can_only_add_members_with_the_member_function(): void
    {
        $chefUser = User::factory()->create(['role' => 'user', 'statut' => 'actif']);
        $chef = $this->createPersonnel($chefUser);
        $nouveauMembre = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $fonctionMembre = Fonction::create(['libelle' => Fonction::MEMBRE]);
        $fonctionExpert = Fonction::create(['libelle' => 'Expert technique']);
        $projet->membres()->attach($chef->id, ['fonction_id' => $fonctionChef->id]);

        Sanctum::actingAs($chefUser);

        $this->postJson('/api/projets/'.$projet->id.'/membres', [
            'personnel_id' => $nouveauMembre->id,
            'fonction_id' => $fonctionExpert->id,
        ])->assertForbidden();

        $this->postJson('/api/projets/'.$projet->id.'/membres', [
            'personnel_id' => $nouveauMembre->id,
            'fonction_id' => $fonctionMembre->id,
        ])->assertCreated();
    }

    public function test_admin_cannot_demote_the_only_active_project_lead(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $chef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $fonctionMembre = Fonction::create(['libelle' => Fonction::MEMBRE]);
        $projet->membres()->attach($chef->id, ['fonction_id' => $fonctionChef->id]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/projets/'.$projet->id.'/membres/'.$chef->id, [
            'fonction_id' => $fonctionMembre->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Désignez d’abord un autre chef de projet avant de modifier cette fonction.');

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $chef->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
    }

    public function test_project_lead_transfer_requires_an_active_project_member_as_replacement(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $ancienChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $autrePersonnel = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $projet->membres()->attach($ancienChef->id, ['fonction_id' => $fonctionChef->id]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/projets/'.$projet->id.'/transfert-chef', [
            'ancien_chef_id' => $ancienChef->id,
            'nouveau_chef_id' => $autrePersonnel->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Le nouveau chef doit être un membre actif de ce projet.');

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $ancienChef->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
    }

    public function test_admin_cannot_remove_project_lead_without_transferring_the_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $ancienChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $projet->membres()->attach($ancienChef->id, ['fonction_id' => $fonctionChef->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/projets/'.$projet->id.'/membres/'.$ancienChef->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Transférez le rôle à un membre actif avant de retirer le chef de projet.');

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $ancienChef->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
    }

    public function test_project_lead_cannot_be_removed_with_open_tasks_during_transfer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $ancienChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $nouveauChef = $this->createPersonnel(User::factory()->create(['statut' => 'actif']));
        $projet = $this->createProjet();
        $fonctionChef = Fonction::create(['libelle' => Fonction::CHEF_DE_PROJET]);
        $fonctionMembre = Fonction::create(['libelle' => 'Membre']);
        $projet->membres()->attach($ancienChef->id, ['fonction_id' => $fonctionChef->id]);
        $projet->membres()->attach($nouveauChef->id, ['fonction_id' => $fonctionMembre->id]);
        $this->createTache($projet, $ancienChef, '2030-05-20');

        Sanctum::actingAs($admin);

        $this->postJson('/api/projets/'.$projet->id.'/transfert-chef', [
            'ancien_chef_id' => $ancienChef->id,
            'nouveau_chef_id' => $nouveauChef->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ce membre a encore des tâches en cours : réaffectez-les ou terminez-les avant de le retirer.');

        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $ancienChef->id,
            'fonction_id' => $fonctionChef->id,
            'date_retrait' => null,
        ]);
        $this->assertDatabaseHas('personnel_projet', [
            'projet_id' => $projet->id,
            'personnel_id' => $nouveauChef->id,
            'fonction_id' => $fonctionMembre->id,
            'date_retrait' => null,
        ]);
    }

    public function test_non_admin_cannot_transfer_project_lead(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $projet = $this->createProjet();

        Sanctum::actingAs($user);

        $this->postJson('/api/projets/'.$projet->id.'/transfert-chef', [
            'ancien_chef_id' => 1,
            'nouveau_chef_id' => 2,
        ])->assertForbidden();
    }

    public function test_duplicate_personnel_names_are_allowed_but_oversized_full_names_are_rejected(): void
    {
        User::factory()->create(['name' => 'Alex Martin']);
        User::factory()->create(['name' => 'Alex Martin']);

        $this->assertDatabaseCount('users', 2);

        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/personnels', [
            'nom' => str_repeat('N', 192),
            'prenom' => str_repeat('P', 100),
            'matricule' => 'MAT-TEST-001',
            'poste' => 'Membre',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('nom');
    }

    public function test_new_personnel_access_is_always_created_as_a_member(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = Personnel::create([
            'nom' => 'Nouveau',
            'prenom' => 'Membre',
            'matricule' => 'MAT-NOUVEAU',
            'poste' => 'Membre',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/personnels/'.$personnel->id.'/acces', [
            'email' => 'new-member@example.test',
            'password' => 'password-123',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->postJson('/api/personnels/'.$personnel->id.'/acces', [
            'email' => 'new-member@example.test',
            'password' => 'password-123',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'user');
    }

    public function test_admin_user_list_exposes_personnel_matricule_at_top_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $personnel = $this->createPersonnel($admin);

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.0.matricule', $personnel->matricule)
            ->assertJsonPath('data.0.personnel.matricule', $personnel->matricule);
    }

    public function test_message_deletions_are_included_in_the_discussion_poll_for_other_members(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $lecteur = User::factory()->create(['statut' => 'actif']);
        $personnelAuteur = $this->createPersonnel($auteur);
        $personnelLecteur = $this->createPersonnel($lecteur);
        $projet = $this->createProjet();
        $fonctionMembre = Fonction::create(['libelle' => Fonction::MEMBRE]);
        $projet->membres()->attach($personnelAuteur->id, ['fonction_id' => $fonctionMembre->id]);
        $projet->membres()->attach($personnelLecteur->id, ['fonction_id' => $fonctionMembre->id]);
        $message = Message::create([
            'projet_id' => $projet->id,
            'user_id' => $auteur->id,
            'contenu' => 'À supprimer',
        ]);

        Sanctum::actingAs($lecteur);
        $this->getJson('/api/projets/'.$projet->id.'/messages')
            ->assertOk()
            ->assertJsonPath('dernier_id_suppression', 0);

        Sanctum::actingAs($auteur);
        $this->deleteJson('/api/messages/'.$message->id)->assertOk();

        Sanctum::actingAs($lecteur);
        $this->getJson('/api/projets/'.$projet->id.'/messages?apres_id='.$message->id.'&apres_suppression_id=0')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('suppressions.0.message_id', $message->id)
            ->assertJsonPath('dernier_id_suppression', 1);
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
            'matricule' => 'MAT-'.$user->id,
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
