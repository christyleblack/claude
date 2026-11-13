<?php

namespace App\Commands;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Affecte un rôle à un utilisateur depuis la ligne de commande, notamment pour
 * créer le premier administrateur. Journalisé (auteur : système).
 */
class DroitsAffecter extends BaseCommand
{
    protected $group       = 'Droits';
    protected $name        = 'droits:affecter';
    protected $description = 'Affecte un rôle à un utilisateur sur une entité, à partir d\'aujourd\'hui.';
    protected $usage       = 'droits:affecter <identifiant> <CODE_ROLE> <CODE_ENTITE>';
    protected $arguments   = [
        'identifiant' => 'Identifiant (username) de l\'utilisateur',
        'CODE_ROLE'   => 'Code du rôle, par exemple ADMIN_SYSTEME',
        'CODE_ENTITE' => 'Code de l\'entité, par exemple DCMA',
    ];

    public function run(array $params)
    {
        [$identifiant, $codeRole, $codeEntite] = $params + [null, null, null];

        if ($codeEntite === null) {
            CLI::error('Usage : php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        $utilisateur = model(UserModel::class)->where('username', $identifiant)->first();
        $role        = (new RoleModel())->where('code', strtoupper($codeRole))->first();
        $entite      = db_connect()->table('entite')->where('code', strtoupper($codeEntite))->get()->getRowArray();

        foreach (['Utilisateur' => $utilisateur, 'Rôle' => $role, 'Entité' => $entite] as $quoi => $valeur) {
            if ($valeur === null) {
                CLI::error("{$quoi} introuvable.");

                return EXIT_USER_INPUT;
            }
        }

        $model = new AffectationModel();
        $id    = $model->insert([
            'utilisateur_id' => $utilisateur->id,
            'role_id'        => $role['id'],
            'entite_id'      => $entite['id'],
            'date_debut'     => date('Y-m-d'),
        ]);

        if ($id === false) {
            CLI::error(implode(' ', $model->errors()));

            return EXIT_ERROR;
        }

        CLI::write("Affectation n° {$id} : {$identifiant} est {$role['libelle']} sur {$entite['nom']}.", 'green');

        return EXIT_SUCCESS;
    }
}
