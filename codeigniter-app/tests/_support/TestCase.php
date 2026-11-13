<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Base des tests qui touchent la base de données.
 *
 * Chaque test repart d'une session vide et de services neufs.
 *
 * Chaque requête HTTP simulée passe la connexion sous le rôle applicatif soumis au
 * cloisonnement (filtre ContexteDroits). Si une requête s'interrompt (exception),
 * la connexion partagée peut y rester : chaque test repart donc en propriétaire,
 * pour que les migrations et la préparation des données ne soient pas cloisonnées.
 */
abstract class TestCase extends CIUnitTestCase
{
    protected function setUp(): void
    {
        // Ni session ni services hérités d'un test précédent (utilisateur connecté, droits en cache)
        $_SESSION = [];
        $this->resetServices();
        $this->redevenirProprietaire();

        parent::setUp();
    }

    /**
     * À appeler après une requête simulée interrompue par une exception (page 404…),
     * avant de relire la base pour vérifier qu'elle n'a pas changé.
     */
    protected function redevenirProprietaire(): void
    {
        $db = db_connect();
        $db->query('RESET ROLE');
        $db->query("SELECT set_config('app.utilisateur_id', '', false)");
    }
}
