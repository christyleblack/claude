<?php

namespace App\Controllers\Referentiel;

use App\Controllers\GestionController;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Outils communs du module Référentiel. Les droits sont aussi garantis par la base (RLS).
 */
abstract class ReferentielController extends GestionController
{
    protected array $contraintes = [
        'armement_suivi_par_serie' => 'Un article d\'armement est suivi par numéro de série (RG-07).',
        'munition_suivie_par_lot'  => 'Une munition est suivie par lot (RG-07).',
        'serie_en_unites'          => 'Un article suivi par numéro de série se gère à l\'unité (U).',
        'article_force_article_id_force_id_key' => 'Cette force a déjà une fiche pour cet article (RG-09).',
        'categorie_filiere_id_code_key'          => 'Ce code de catégorie existe déjà dans la filière.',
    ];

    protected function peutGerer(?int $filiereId = null): bool
    {
        return service('droits')->peutFiliere('referentiel.gerer', $filiereId);
    }

    protected function refuser(string $message = 'Vous n\'avez pas le droit de gérer cette partie du référentiel.'): RedirectResponse
    {
        return redirect()->back()->withInput()->with('erreurs', [$message]);
    }
}
