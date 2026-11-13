<?php

namespace App\Controllers\Organisation;

use App\Controllers\GestionController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Outils communs aux contrôleurs du module Organisation.
 */
abstract class OrganisationController extends GestionController
{
    /**
     * Droit de gérer l'organisation : sur une entité, ou au niveau national.
     */
    protected function peutGerer(?int $entiteId = null, bool $nationale = false): bool
    {
        return service('droits')->peut('organisation.gerer', $entiteId, $nationale);
    }

    protected function refuser(): RedirectResponse
    {
        return redirect()->back()->withInput()->with('erreurs', ['Vous n\'avez pas le droit de gérer cet élément de l\'organisation (hors de votre périmètre).']);
    }
}
