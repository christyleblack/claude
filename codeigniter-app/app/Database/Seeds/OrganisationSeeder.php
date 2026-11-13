<?php

namespace App\Database\Seeds;

use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Organisation de démonstration : DCMA, ses six services, les sept forces
 * du cahier des charges, et quelques unités, dépôts, sites et emplacements.
 *
 * TOUTES LES DONNÉES SONT FICTIVES (cahier des charges, section 7.4).
 * Les intitulés des forces et des services sont ceux du cahier des charges ;
 * les unités, dépôts, sites et adresses sont inventés.
 *
 * php spark db:seed OrganisationSeeder
 */
class OrganisationSeeder extends Seeder
{
    private array $types = [];

    public function run()
    {
        if ((new EntiteModel())->countAllResults() > 0) {
            throw new RuntimeException('La base contient déjà des entités : jeu de démonstration non chargé.');
        }

        $typeModel = new TypeEntiteModel();
        foreach ([
            ['DIRECTION_CENTRALE', 'Direction centrale', false],
            ['SERVICE_CENTRAL', 'Service central', true],
            ['FORCE', 'Force', false],
            ['DIRECTION_MATERIEL', 'Direction du matériel de force', false],
            ['GRANDE_UNITE', 'Grande unité', false],
            ['UNITE', 'Unité', false],
            ['DEPOT', 'Dépôt', true],
            ['MAGASIN', 'Magasin', true],
        ] as [$code, $libelle, $stockeur]) {
            $this->types[$code] = $typeModel->insert(['code' => $code, 'libelle' => $libelle, 'est_stockeur' => $stockeur]);
        }

        $dcma = $this->entite('DCMA', 'Direction Centrale du Matériel des Armées', 'DIRECTION_CENTRALE');
        foreach ([
            ['SERV-ESS', 'Service Essence'],
            ['SERV-TRL', 'Service Transport logistique'],
            ['SERV-APP', 'Service Approvisionnement'],
            ['SEPCT', 'Service Étude et Planification (SEPCT)'],
            ['SAMO', 'Service Armement Munition Optique (SAMO)'],
            ['ECCRA', 'ECCRA'],
        ] as [$code, $nom]) {
            $this->entite($code, $nom, 'SERVICE_CENTRAL', $dcma, $dcma);
        }

        $forces = [
            ['TERRE', 'Armée de Terre'],
            ['AIR', 'Armée de l\'Air'],
            ['GN', 'Gendarmerie Nationale'],
            ['BNSP', 'Brigade Nationale des Sapeurs-Pompiers'],
            ['GCA', 'Groupement Central des Armées'],
            ['BSIR', 'BSIR'],
            ['BVDP', 'BVDP'],
        ];

        $forceModel = new ForceModel();
        foreach ($forces as $i => [$code, $nom]) {
            $racine = $this->entite($code, $nom, 'FORCE');
            $forceModel->insert(['code' => $code, 'nom' => $nom, 'entite_racine_id' => $racine]);

            // Direction du matériel de la force, ravitaillée par la DCMA
            $dm = $this->entite($code . '-DM', 'Direction du matériel – ' . $nom, 'DIRECTION_MATERIEL', $racine, $dcma);

            // Données inventées : une unité et un dépôt par force
            $unite = $this->entite($code . '-U1', '1re unité (fictive) – ' . $nom, 'UNITE', $racine, $dm);
            $depot = $this->entite($code . '-D1', 'Dépôt n° 1 (fictif) – ' . $nom, 'DEPOT', $dm, $dm);

            $site = (new SiteModel())->insert([
                'code'      => $code . '-S1',
                'nom'       => 'Site fictif du dépôt n° 1 – ' . $nom,
                'adresse'   => 'Adresse fictive n° ' . ($i + 1),
                'entite_id' => $depot,
                'actif'     => true,
            ]);

            $emplacements = new EmplacementModel();
            $magasin      = $emplacements->insert(['site_id' => $site, 'code' => 'MAG-A', 'libelle' => 'Magasin A', 'actif' => true]);
            foreach (['R01', 'R02'] as $rayon) {
                $emplacements->insert(['site_id' => $site, 'parent_id' => $magasin, 'code' => 'MAG-A-' . $rayon, 'libelle' => 'Rayonnage ' . $rayon, 'actif' => true]);
            }
        }
    }

    private function entite(string $code, string $nom, string $type, ?int $parentH = null, ?int $parentL = null): int
    {
        $model = new EntiteModel();
        $id    = $model->insert([
            'code'                   => $code,
            'nom'                    => $nom,
            'type_entite_id'         => $this->types[$type],
            'parent_hierarchique_id' => $parentH,
            'parent_logistique_id'   => $parentL,
            'actif'                  => true,
        ]);

        if ($id === false) {
            throw new RuntimeException("Entité {$code} : " . implode(' ', $model->errors()));
        }

        return (int) $id;
    }
}
