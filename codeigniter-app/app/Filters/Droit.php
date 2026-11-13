<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtre de route : exige une permission, par exemple ['filter' => 'droit:organisation.gerer'].
 * La vérification fine sur l'entité concernée reste faite par le contrôleur (et par la base).
 */
class Droit implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }

        foreach ((array) $arguments as $permission) {
            if (! service('droits')->peut($permission)) {
                return redirect()->to('/')->with('erreur', 'Vous n\'avez pas l\'autorisation nécessaire pour cette action.');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
