<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Vérifie la chaîne d'empreintes du journal des mouvements (H-05) : toute altération
 * directe en base, même par un administrateur, est signalée.
 * À planifier régulièrement (cron) et à lancer avant chaque inventaire de contrôle.
 */
class StocksVerifier extends BaseCommand
{
    protected $group       = 'Stocks';
    protected $name        = 'stocks:verifier';
    protected $description = 'Vérifie l\'intégrité du journal des mouvements (empreintes chaînées).';

    public function run(array $params)
    {
        $db        = db_connect();
        $nombre    = (int) $db->query('SELECT count(*) AS n FROM mouvement')->getRow()->n;
        $premier   = $db->query('SELECT stocks_verifier_chaine() AS id')->getRow()->id;

        if ($premier === null) {
            CLI::write("Journal intact : {$nombre} mouvement(s) vérifié(s).", 'green');

            return EXIT_SUCCESS;
        }

        CLI::error("ALTÉRATION DÉTECTÉE : la chaîne est rompue au mouvement n° {$premier}. Le journal a été modifié hors de l'application.");

        return EXIT_ERROR;
    }
}
