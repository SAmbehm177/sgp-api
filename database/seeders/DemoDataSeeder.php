<?php

namespace Database\Seeders;

use App\Models\Fonction;
use App\Models\Message;
use App\Models\Personnel;
use App\Models\Projet;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const PERSONNELS = [
        ['Ariel', 'Traore', 'Responsable produit'],
        ['Camille', 'Yaba', 'Développeur'],
        ['Yohana', 'Waga', 'Designer UX/UI'],
        ['Melvine', 'Kouamé', 'Développeur'],
        ['Kouadio', 'N’Guessan', 'Analyste métier'],
        ['Aïcha', 'Koné', 'Développeur'],
        ['Mariam', 'Diabaté', 'Responsable qualité'],
        ['Koffi', 'Kouassi', 'Administrateur système'],
        ['Aya', 'Bamba', 'Développeuse'],
        ['Serge', 'Yao', 'Chef de projet'],
        ['Nadia', 'Fofana', 'Designer UX/UI'],
        ['Adama', 'Touré', 'Développeur'],
        ['Aminata', 'Ouattara', 'Analyste métier'],
        ['Jean-Marc', 'Koffi', 'Responsable qualité'],
        ['Grâce', 'Aka', 'Développeuse'],
        ['Franck', 'Dago', 'Administrateur système'],
        ['Sandrine', 'Gnahoré', 'Développeuse'],
        ['Ismaël', 'Coulibaly', 'Chef de projet'],
    ];

    private const PROJETS = [
        ['Portail citoyen', 'Refonte des démarches en ligne et simplification du parcours des usagers.', 'en cours'],
        ['Application mobile', 'Conception d’une application mobile accessible pour les équipes de terrain.', 'en cours'],
        ['Observatoire des données', 'Centralisation et visualisation des indicateurs de service.', 'en cours'],
        ['Modernisation du SI', 'Mise à niveau des services et rationalisation des échanges inter-applicatifs.', 'en cours'],
        ['Espace partenaires', 'Création d’un espace sécurisé de partage avec les partenaires.', 'en cours'],
        ['Gestion documentaire', 'Classement, recherche et archivage des documents opérationnels.', 'terminé'],
        ['Centre de support', 'Mise en place d’un portail de demandes et de suivi des incidents.', 'terminé'],
        ['Migration des données', 'Préparation et transfert des données vers la nouvelle plateforme.', 'en cours'],
        ['Outil de planification', 'Planification des interventions et suivi des disponibilités.', 'annulé'],
        ['Tableau de pilotage', 'Indicateurs consolidés pour le suivi des activités et des objectifs.', 'en cours'],
    ];

    private const TACHES = [
        ['Cadrer les besoins', 'Recueillir les attentes des utilisateurs et valider le périmètre fonctionnel.'],
        ['Rédiger les spécifications', 'Décrire les parcours, les règles de gestion et les critères de validation.'],
        ['Préparer les maquettes', 'Proposer les écrans et vérifier leur cohérence avec les usages attendus.'],
        ['Développer le service', 'Implémenter les fonctionnalités prévues et documenter les choix techniques.'],
        ['Intégrer les données', 'Définir les échanges et contrôler la qualité des données intégrées.'],
        ['Tester les parcours', 'Vérifier les principaux scénarios et corriger les anomalies observées.'],
        ['Préparer le déploiement', 'Organiser la mise en production et définir les étapes de retour arrière.'],
        ['Présenter les résultats', 'Partager les livrables, recueillir les retours et formaliser les suites.'],
    ];

    private const MESSAGES = [
        'Le point d’avancement est prêt. Les éléments réalisés et les prochaines étapes sont dans le suivi du projet.',
        'Merci pour les retours sur cette itération. Je les ajoute à la liste des actions à traiter.',
        'La prochaine revue permettra de valider les parcours et de confirmer les priorités.',
        'Pensez à mettre à jour vos tâches avant notre point d’équipe de cette semaine.',
    ];

    public function run(): void
    {
        $fonctionChef = Fonction::where('libelle', Fonction::CHEF_DE_PROJET)->firstOrFail();
        $fonctionMembre = Fonction::where('libelle', Fonction::MEMBRE)->firstOrFail();
        $personnels = $this->creerPersonnels();

        foreach (self::PROJETS as $index => [$titre, $description, $statut]) {
            $debut = now()->copy()->subMonths(($index % 6) + 1)->startOfMonth();
            $projet = Projet::updateOrCreate(
                ['titre' => $titre],
                [
                    'description' => $description,
                    'date_debut' => $debut->toDateString(),
                    'date_fin' => $debut->copy()->addMonths(4)->toDateString(),
                    'statut' => $statut,
                ]
            );

            $membres = $this->affecterMembres($projet, $personnels, $index, $fonctionChef->id, $fonctionMembre->id);
            $this->creerTaches($projet, $membres, $debut, $statut);
            $this->creerMessages($projet, $membres);
        }
    }

    /**
     * @return list<Personnel>
     */
    private function creerPersonnels(): array
    {
        $personnels = [];

        foreach (self::PERSONNELS as $index => [$prenom, $nom, $poste]) {
            $numero = $index + 1;
            $email = sprintf('demo.membre%02d@example.test', $numero);
            $user = User::firstOrNew(['email' => $email]);
            $demoPassword = config('app.seed_demo.password');

            if (! $user->exists) {
                $user->forceFill([
                    'password' => $demoPassword ?? Str::random(48),
                    'role' => 'user',
                    'statut' => 'actif',
                    'must_change_password' => $demoPassword !== null,
                ]);
            } elseif ($demoPassword !== null) {
                $user->forceFill([
                    'password' => $demoPassword,
                    'must_change_password' => true,
                ]);
            }

            $user->forceFill(['name' => "$prenom $nom"])->save();

            $personnels[] = Personnel::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'matricule' => sprintf('DEMO-%04d', $numero),
                    'poste' => $poste,
                ]
            );
        }

        return $personnels;
    }

    /**
     * @param  list<Personnel>  $personnels
     * @return list<Personnel>
     */
    private function affecterMembres(
        Projet $projet,
        array $personnels,
        int $index,
        int $fonctionChefId,
        int $fonctionMembreId
    ): array {
        $membres = [];
        $affectations = [];

        for ($offset = 0; $offset < 5; $offset++) {
            $personnel = $personnels[($index + $offset * 3) % count($personnels)];
            $membres[] = $personnel;
            $affectations[$personnel->id] = [
                'fonction_id' => $offset === 0 ? $fonctionChefId : $fonctionMembreId,
                'date_retrait' => null,
            ];
        }

        $projet->membres()->syncWithoutDetaching($affectations);

        return $membres;
    }

    /**
     * @param  list<Personnel>  $membres
     */
    private function creerTaches(Projet $projet, array $membres, $debut, string $statutProjet): void
    {
        $statuts = match ($statutProjet) {
            'terminé' => array_fill(0, count(self::TACHES), 'terminé'),
            'annulé' => ['terminé', 'terminé', 'annulé', 'annulé', 'annulé', 'annulé', 'annulé', 'annulé'],
            default => ['terminé', 'terminé', 'en cours', 'en cours', 'en cours', 'en cours', 'annulé', 'en cours'],
        };
        $priorites = ['haute', 'moyenne', 'basse'];

        Tache::withoutEvents(function () use ($projet, $membres, $debut, $statuts, $priorites): void {
            foreach (self::TACHES as $index => [$titre, $description]) {
                $dateDebut = $debut->copy()->addDays($index * 5);
                $projet->taches()->updateOrCreate(
                    ['titre' => $titre],
                    [
                        'description' => $description,
                        'date_debut' => $dateDebut->toDateString(),
                        'date_echeance' => $dateDebut->copy()->addDays(12)->toDateString(),
                        'statut' => $statuts[$index],
                        'priorite' => $priorites[$index % count($priorites)],
                        'responsable_id' => $membres[$index % count($membres)]->id,
                    ]
                );
            }
        });
    }

    /**
     * @param  list<Personnel>  $membres
     */
    private function creerMessages(Projet $projet, array $membres): void
    {
        Message::withoutEvents(function () use ($projet, $membres): void {
            foreach (self::MESSAGES as $index => $contenu) {
                $userId = $membres[$index % count($membres)]->user_id;
                Message::firstOrCreate(
                    [
                        'projet_id' => $projet->id,
                        'user_id' => $userId,
                        'contenu' => $contenu,
                    ],
                    ['destinataire_id' => null]
                );
            }
        });
    }
}
