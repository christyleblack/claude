<?php

namespace App\Database\Seeds;

use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Référentiel de démonstration : les trois filières du cahier des charges (section 2.2),
 * leurs catégories et quelques articles d'exemple.
 *
 * DONNÉES FICTIVES : les désignations sont génériques, sans caractéristique réelle.
 * Nécessite l'organisation (OrganisationSeeder) pour les services gestionnaires.
 *
 * php spark db:seed ReferentielSeeder
 */
class ReferentielSeeder extends Seeder
{
    public function run()
    {
        if ((new FiliereModel())->countAllResults() > 0) {
            throw new RuntimeException('La base contient déjà des filières : référentiel de démonstration non chargé.');
        }

        $services = [];
        foreach (['SERV-TRL', 'SERV-ESS', 'SAMO'] as $code) {
            $service = $this->db->table('entite')->where('code', $code)->get()->getRowArray();
            if ($service === null) {
                throw new RuntimeException("Service {$code} introuvable : charger d'abord OrganisationSeeder.");
            }
            $services[$code] = (int) $service['id'];
        }

        $filieres   = new FiliereModel();
        $categories = new CategorieModel();
        $articles   = new ArticleModel();

        foreach ([
            ['ENGINS', 'Engins roulants', 'SERV-TRL', [
                // [code, libellé, mode imposé, [[codification, désignation, unité, sensibilité], …]]
                ['VEH-SERVICE', 'Véhicules de service', 'serie', [['ENG-0001', 'Véhicule de liaison tout-terrain (exemple)', 'U', 'courant']]],
                ['VEH-TRANSPORT', 'Véhicules de transport', 'serie', [['ENG-0101', 'Camion de transport de troupes (exemple)', 'U', 'courant'], ['ENG-0102', 'Car de transport (exemple)', 'U', 'courant']]],
                ['VEH-COMBAT', 'Véhicules de combat', 'serie', [['ENG-0201', 'Véhicule blindé (exemple)', 'U', 'sensible']]],
                ['MOTOS', 'Motos', 'serie', [['ENG-0301', 'Moto tout-terrain (exemple)', 'U', 'courant']]],
            ]],
            ['CARBURANTS', 'Carburants et lubrifiants', 'SERV-ESS', [
                ['ESSENCE', 'Essence', 'quantite', [['CAR-0001', 'Essence sans plomb', 'L', 'courant']]],
                ['GASOIL', 'Gas-oil', 'quantite', [['CAR-0101', 'Gas-oil', 'L', 'courant']]],
                ['LUBRIFIANTS', 'Lubrifiants', 'quantite', [['CAR-0201', 'Huile moteur (exemple)', 'L', 'courant']]],
            ]],
            ['ARMEMENT', 'Armement, munitions, optique', 'SAMO', [
                ['ARMES', 'Armes', 'serie', [['ARM-0001', 'Arme individuelle (exemple)', 'U', 'armement']]],
                ['MUNITIONS', 'Munitions', 'lot', [['MUN-0001', 'Munition d\'exercice (exemple)', 'U', 'munition']]],
                ['OPTIQUE', 'Optique', 'serie', [['OPT-0001', 'Jumelles d\'observation (exemple)', 'U', 'sensible']]],
            ]],
        ] as [$codeFiliere, $libelle, $service, $categoriesFiliere]) {
            $filiere = (int) $filieres->insert(['code' => $codeFiliere, 'libelle' => $libelle, 'service_gestionnaire_id' => $services[$service], 'actif' => true]);

            foreach ($categoriesFiliere as [$codeCategorie, $libelleCategorie, $mode, $articlesCategorie]) {
                $categorie = (int) $categories->insert(['filiere_id' => $filiere, 'code' => $codeCategorie, 'libelle' => $libelleCategorie, 'mode_suivi_impose' => $mode, 'actif' => true]);

                foreach ($articlesCategorie as [$codification, $designation, $unite, $sensibilite]) {
                    $id = $articles->insert([
                        'codification' => $codification, 'designation' => $designation, 'unite_gestion' => $unite,
                        'filiere_id' => $filiere, 'categorie_id' => $categorie, 'mode_suivi' => $mode, 'sensibilite' => $sensibilite, 'actif' => true,
                    ]);
                    if ($id === false) {
                        throw new RuntimeException("Article {$codification} : " . implode(' ', $articles->errors()));
                    }
                }
            }
        }
    }
}
