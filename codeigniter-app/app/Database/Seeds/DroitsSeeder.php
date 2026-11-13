<?php

namespace App\Database\Seeds;

use App\Models\Droits\RoleModel;
use CodeIgniter\Database\Seeder;

/**
 * Rôles correspondant aux dix profils types du cahier des charges (section 4.1).
 * Ce sont des valeurs de départ, modifiables ensuite dans Administration > Rôles.
 *
 * Les permissions des futurs modules (demandes, stocks, inventaires…) seront
 * ajoutées à ces rôles au fur et à mesure.
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
            ['GESTIONNAIRE_FILIERE', 'Gestionnaire de filière (service central)', 'nationale', []],
            ['CHEF_LOGISTIQUE_FORCE', 'Chef du service logistique de force', 'entite', []],
            ['GESTIONNAIRE_DEPOT', 'Gestionnaire de dépôt / magasinier', 'entite', []],
            ['DEMANDEUR', 'Demandeur (unité)', 'entite', []],
            ['AUDITEUR', 'Auditeur / contrôleur', 'nationale', ['audit.consulter']],
            ['ADMIN_FONCTIONNEL', 'Administrateur fonctionnel (SEPCT)', 'nationale', ['organisation.gerer', 'administration.roles']],
            ['OPERATEUR_DEPANNAGE', 'Opérateur dépannage (SEPCT)', 'nationale', []],
            ['CONDUCTEUR', 'Conducteur', 'entite', []],
        ] as [$code, $libelle, $portee, $permissions]) {
            if ($roles->where('code', $code)->first() !== null) {
                continue;
            }

            $id = $roles->insert(['code' => $code, 'libelle' => $libelle, 'portee' => $portee, 'actif' => true]);
            $roles->definirPermissions((int) $id, $permissions);
        }
    }
}
