<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtre global : à chaque requête HTTP, la connexion à la base passe sous le rôle
 * applicatif (soumis au cloisonnement) et transmet l'utilisateur connecté à PostgreSQL,
 * qui l'utilise dans les politiques de sécurité et le journal d'audit.
 */
class ContexteDroits implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $db   = db_connect();
        $role = config('Droits')->roleApplication;

        if ($role !== '') {
            $db->query('SET ROLE ' . $db->escapeIdentifier($role));
        }

        $db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) (auth()->id() ?? '')]);
        service('droits')->oublier();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $db = db_connect();
        $db->query("SELECT set_config('app.utilisateur_id', '', false)");
        $db->query('RESET ROLE');
    }
}
