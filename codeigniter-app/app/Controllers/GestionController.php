<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;

/**
 * Outils communs aux contrôleurs à formulaires : élément introuvable, lecture des champs,
 * enregistrement avec retour au formulaire en cas d'erreur de validation ou de refus de la base.
 */
abstract class GestionController extends BaseController
{
    /**
     * Messages lisibles pour les contraintes de la base, par nom de contrainte.
     *
     * @var array<string, string>
     */
    protected array $contraintes = [];

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

    private function contrainteEnCause(string $message): ?string
    {
        foreach (array_keys($this->contraintes) as $nom) {
            if (str_contains($message, '"' . $nom . '"')) {
                return $nom;
            }
        }

        return null;
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
            $message = match (true) {
                // Règles métier signalées par la base elle-même (messages en français des déclencheurs)
                (bool) preg_match('/(Cycle refusé|Délégation refusée|Mode de suivi refusé|Inventaire refusé)[^\n]*/u', $e->getMessage(), $m) => preg_replace('/\s+CONTEXT:.*$/s', '', $m[0]),
                str_contains($e->getMessage(), 'row-level security') => 'Enregistrement refusé : il sortirait de votre périmètre ou de vos droits.',
                ($nom = $this->contrainteEnCause($e->getMessage())) !== null => $this->contraintes[$nom],
                default => 'Enregistrement refusé par la base de données : vérifiez les valeurs saisies.',
            };

            return redirect()->back()->withInput()->with('erreurs', [$message]);
        }

        if ($resultat === false) {
            return redirect()->back()->withInput()->with('erreurs', $model->errors());
        }

        return $resultat;
    }
}
