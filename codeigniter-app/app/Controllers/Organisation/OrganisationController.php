<?php

namespace App\Controllers\Organisation;

use App\Controllers\BaseController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;

/**
 * Outils communs aux contrôleurs du module Organisation.
 */
abstract class OrganisationController extends BaseController
{
    /**
     * Ligne trouvée, ou page 404.
     */
    protected function trouver(Model $model, int $id): array
    {
        $ligne = $model->find($id);

        if ($ligne === null) {
            throw PageNotFoundException::forPageNotFound('Élément introuvable.');
        }

        return $ligne;
    }

    /**
     * Case à cocher d'un formulaire : absente si décochée.
     */
    protected function case(string $nom): bool
    {
        return $this->request->getPost($nom) !== null;
    }

    /**
     * Champ texte nettoyé ; un champ vide vaut chaîne vide.
     */
    protected function champ(string $nom): string
    {
        return trim((string) $this->request->getPost($nom));
    }

    /**
     * Enregistre via le modèle ; en cas d'erreur de validation ou de refus
     * par la base (cycle…), renvoie au formulaire avec les messages.
     *
     * @param callable(): (bool|int|string) $operation
     */
    protected function enregistrer(Model $model, callable $operation): RedirectResponse|int|string|true
    {
        try {
            $resultat = $operation();
        } catch (DatabaseException $e) {
            // Contraintes garanties par la base (voir la migration CreerOrganisation)
            $message = str_contains($e->getMessage(), 'Cycle refusé')
                ? preg_replace('/^.*?(Cycle refusé[^\n]*?)(\s+CONTEXT:.*)?$/s', '$1', $e->getMessage())
                : 'Enregistrement refusé par la base de données : vérifiez les valeurs saisies.';

            return redirect()->back()->withInput()->with('erreurs', [$message]);
        }

        if ($resultat === false) {
            return redirect()->back()->withInput()->with('erreurs', $model->errors());
        }

        return $resultat;
    }
}
