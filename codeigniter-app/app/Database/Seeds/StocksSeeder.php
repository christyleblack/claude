<?php

namespace App\Database\Seeds;

use App\Libraries\SaisieMouvements;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Mouvements de démonstration dans le dépôt fictif de l'Armée de Terre.
 *
 * DONNÉES FICTIVES. Nécessite OrganisationSeeder, ReferentielSeeder et au moins un compte
 * utilisateur (le plus ancien est enregistré comme auteur des mouvements).
 *
 * php spark db:seed StocksSeeder
 */
class StocksSeeder extends Seeder
{
    public function run()
    {
        if ($this->db->table('mouvement')->countAllResults() > 0) {
            throw new RuntimeException('La base contient déjà des mouvements : jeu de démonstration non chargé.');
        }

        $auteur = $this->db->table('users')->orderBy('id')->limit(1)->get()->getRow();
        if ($auteur === null) {
            throw new RuntimeException('Créez d\'abord un compte utilisateur : il sera l\'auteur des mouvements de démonstration.');
        }
        // Hors requête HTTP, l'auteur est transmis à la base comme le ferait l'application
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $auteur->id]);

        $emplacement = function (string $site, string $code): string {
            $ligne = $this->db->query('SELECT e.id FROM emplacement e JOIN site s ON s.id = e.site_id WHERE s.code = ? AND e.code = ?', [$site, $code])->getRow();
            if ($ligne === null) {
                throw new RuntimeException("Emplacement {$site} {$code} introuvable : charger d'abord OrganisationSeeder.");
            }

            return (string) $ligne->id;
        };
        $r01 = $emplacement('TERRE-S1', 'MAG-A-R01');
        $r02 = $emplacement('TERRE-S1', 'MAG-A-R02');

        $saisie = new SaisieMouvements($this->db);
        foreach ([
            ['type' => 'entree', 'codification' => 'CAR-0101', 'quantite' => '12000', 'destination' => $r01, 'motif' => 'Livraison fictive n° 1'],
            ['type' => 'entree', 'codification' => 'CAR-0001', 'quantite' => '4500', 'destination' => $r01, 'motif' => 'Livraison fictive n° 1'],
            ['type' => 'entree', 'codification' => 'ENG-0001', 'numeros_serie' => "VLTT-FICT-0001\nVLTT-FICT-0002\nVLTT-FICT-0003", 'destination' => $r01, 'motif' => 'Dotation fictive'],
            ['type' => 'entree', 'codification' => 'MUN-0001', 'numero_lot' => 'LOT-FICT-2026-01', 'date_peremption' => '2031-06-30', 'quantite' => '2000', 'destination' => $r02, 'motif' => 'Dotation fictive'],
            ['type' => 'entree', 'codification' => 'ARM-0001', 'numeros_serie' => "ARM-FICT-0001\nARM-FICT-0002", 'destination' => $r02, 'motif' => 'Dotation fictive'],
            ['type' => 'transfert', 'codification' => 'CAR-0101', 'quantite' => '2000', 'origine' => $r01, 'destination' => $r02, 'motif' => 'Réorganisation fictive'],
            ['type' => 'sortie', 'codification' => 'CAR-0101', 'quantite' => '850', 'origine' => $r01, 'motif' => 'Consommation fictive'],
        ] as $mouvement) {
            $saisie->enregistrer($mouvement);
        }

        $this->db->query("SELECT set_config('app.utilisateur_id', '', false)");
    }
}
