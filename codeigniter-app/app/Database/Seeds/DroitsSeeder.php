<?php

namespace App\Database\Seeds;

use App\Models\Droits\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Rôles correspondant aux dix profils types du cahier des charges (section 4.1).
 * Ce sont des valeurs de départ, modifiables ensuite dans Administration > Rôles.
 *
 * Relancé sur une base existante, il ajoute aux rôles déjà présents les permissions
 * par défaut qui leur manquent (nouveaux modules) ; il n'en retire jamais. Une
 * permission par défaut retirée à la main reviendrait donc si on le relance.
 *
 * php spark db:seed DroitsSeeder
 */
class DroitsSeeder extends Seeder
{
    public function run()
    {
        $roles = new RoleModel();

        foreach ([
            ['ADMIN_SYSTEME', 'Administrateur système', 'nationale', ['administration.utilisateurs', 'administration.roles', 'audit.consulter']],
            ['DIRECTEUR_DCMA', 'Directeur / cadre DCMA', 'nationale', []],
            ['GESTIONNAIRE_FILIERE', 'Gestionnaire de filière (service central)', 'nationale', ['referentiel.gerer']],
            ['CHEF_LOGISTIQUE_FORCE', 'Chef du service logistique de force', 'entite', ['referentiel.fiche_force']],
            ['GESTIONNAIRE_DEPOT', 'Gestionnaire de dépôt / magasinier', 'entite', ['stocks.mouvementer']],
            ['DEMANDEUR', 'Demandeur (unité)', 'entite', []],
            ['AUDITEUR', 'Auditeur / contrôleur', 'nationale', ['audit.consulter']],
            ['ADMIN_FONCTIONNEL', 'Administrateur fonctionnel (SEPCT)', 'nationale', ['organisation.gerer', 'administration.roles', 'referentiel.gerer']],
            ['OPERATEUR_DEPANNAGE', 'Opérateur dépannage (SEPCT)', 'nationale', []],
            ['CONDUCTEUR', 'Conducteur', 'entite', []],
        ] as [$code, $libelle, $portee, $permissions]) {
            $existant = $roles->where('code', $code)->first();

            if ($existant === null) {
                $id = $roles->insert(['code' => $code, 'libelle' => $libelle, 'portee' => $portee, 'actif' => true]);
                $roles->definirPermissions((int) $id, $permissions);

                continue;
            }

            // Rôle déjà présent : on ajoute les permissions par défaut qui lui manquent
            // (celles des modules apparus depuis), sans jamais en retirer.
            $roles->definirPermissions($existant['id'], array_merge($roles->permissions($existant['id']), $permissions));
        }
    }
}
