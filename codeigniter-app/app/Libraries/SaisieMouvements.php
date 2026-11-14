<?php

namespace App\Libraries;

use App\Models\Referentiel\ArticleModel;
use App\Models\Stocks\BonModel;
use App\Models\Stocks\LotModel;
use App\Models\Stocks\MaterielModel;
use App\Models\Stocks\MouvementModel;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

/**
 * Saisie des mouvements (STK-02). Une saisie peut porter sur plusieurs numéros de série :
 * elle produit alors un mouvement par numéro (RG-11), le tout dans une seule transaction.
 * Les règles de fond (stock jamais négatif, série unique, cohérence du suivi, droits)
 * sont garanties par la base ; ce service prépare les données et donne des messages clairs.
 */
class SaisieMouvements
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array{type: string, codification: string, quantite?: string, numero_lot?: string, date_peremption?: string,
     *              numeros_serie?: string, origine?: string, destination?: string, proprietaire?: string,
     *              date_mouvement?: string, motif?: string} $saisie
     *
     * @return list<int> identifiants des mouvements créés
     *
     * @throws DomainException message lisible pour l'utilisateur
     */
    public function enregistrer(array $saisie): array
    {
        $type = $saisie['type'] ?? '';
        if (! in_array($type, MouvementModel::TYPES_SAISIS, true)) {
            throw new DomainException('Type de mouvement inconnu.');
        }

        $article = (new ArticleModel())->where('codification', strtoupper(trim($saisie['codification'] ?? '')))->first();
        if ($article === null) {
            throw new DomainException('Article inconnu : vérifiez la codification.');
        }

        $origine     = $this->emplacement($saisie['origine'] ?? '', in_array($type, MouvementModel::AVEC_ORIGINE, true), 'd\'origine');
        $destination = $this->emplacement($saisie['destination'] ?? '', in_array($type, MouvementModel::AVEC_DESTINATION, true), 'de destination');

        $commun = [
            'type'                       => $type,
            'article_id'                 => $article['id'],
            'emplacement_origine_id'     => $origine,
            'emplacement_destination_id' => $destination,
            'date_mouvement'             => $this->date($saisie['date_mouvement'] ?? ''),
            'motif'                      => trim($saisie['motif'] ?? '') ?: null,
        ];

        // Dans une transaction, CodeIgniter ne lève pas d'exception par défaut en cas d'erreur
        // de la base : on l'active pour récupérer le message de refus (stock insuffisant…)
        $this->db->transException(true);
        $this->db->transBegin();

        try {
            $ids = match ($article['mode_suivi']) {
                'serie' => $this->parNumerosDeSerie($article, $commun, $saisie),
                'lot'   => [$this->inserer($commun + [
                    'lot_id'                 => $this->lot($article, $type, $saisie),
                    'quantite'               => $this->quantite($saisie['quantite'] ?? ''),
                    'proprietaire_entite_id' => $this->proprietaire($saisie, $origine ?? $destination),
                ])],
                default => [$this->inserer($commun + [
                    'quantite'               => $this->quantite($saisie['quantite'] ?? ''),
                    'proprietaire_entite_id' => $this->proprietaire($saisie, $origine ?? $destination),
                ])],
            };
            // Bon de la saisie (STK-06), dans la même transaction : numéro consommé seulement si tout réussit
            if (isset(BonModel::POUR_MOUVEMENT[$type])) {
                (new BonModel())->emettre(BonModel::POUR_MOUVEMENT[$type], $ids);
            }

            $this->db->transCommit();

            return $ids;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e instanceof DomainException ? $e : new DomainException(self::message($e));
        } finally {
            $this->db->transException(false);
            // Après un échec, CodeIgniter garde transStatus à false sur la connexion : la
            // transaction suivante (transStart/transComplete) serait alors annulée en silence
            $this->db->resetTransStatus();
        }
    }

    /**
     * Annule un mouvement par un mouvement inverse lié (RG-12).
     */
    public function annuler(int $mouvementId, string $motif): int
    {
        if (trim($motif) === '') {
            throw new DomainException('Le motif de l\'annulation est obligatoire.');
        }

        try {
            // La base recopie l'original en inversant origine et destination
            return $this->inserer(['type' => 'annulation', 'mouvement_annule_id' => $mouvementId, 'motif' => trim($motif)]);
        } catch (Throwable $e) {
            throw $e instanceof DomainException ? $e : new DomainException(self::message($e));
        } finally {
            $this->db->resetTransStatus();
        }
    }

    /**
     * Message lisible pour un refus de la base.
     */
    public static function message(Throwable $e): string
    {
        $texte = $e->getMessage();

        return match (true) {
            (bool) preg_match('/(Mouvement refusé|Stock insuffisant|Bon refusé)[^\n]*/u', $texte, $m) => preg_replace('/\s+CONTEXT:.*$/s', '', $m[0]),
            str_contains($texte, 'mouvement_mouvement_annule_id_key') => 'Ce mouvement a déjà été annulé.',
            str_contains($texte, 'row-level security') => 'Vous n\'avez pas le droit de saisir ce mouvement sur cet emplacement.',
            str_contains($texte, 'materiel_serialise_article_id_numero_serie_key') => 'Ce numéro de série existe déjà.',
            default => 'Mouvement refusé par la base de données : vérifiez les valeurs saisies.',
        };
    }

    private function parNumerosDeSerie(array $article, array $commun, array $saisie): array
    {
        $numeros = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $saisie['numeros_serie'] ?? '')))));
        if ($numeros === []) {
            throw new DomainException('Indiquez au moins un numéro de série (un par ligne).');
        }

        $materiels = new MaterielModel();
        $ids       = [];
        foreach ($numeros as $numero) {
            $materiel = $materiels->where(['article_id' => $article['id'], 'numero_serie' => $numero])->first();

            if ($materiel === null) {
                if ($commun['type'] !== 'entree') {
                    throw new DomainException("Numéro de série inconnu : {$numero}.");
                }
                // Première entrée : le matériel est créé, propriété de l'entité indiquée
                $proprietaire = $this->proprietaire($saisie, $commun['emplacement_destination_id']);
                $id           = $materiels->insert(['article_id' => $article['id'], 'numero_serie' => $numero, 'proprietaire_entite_id' => $proprietaire]);
                $materiel     = ['id' => (int) $id, 'proprietaire_entite_id' => $proprietaire];
            }

            $ids[] = $this->inserer($commun + [
                'materiel_id'            => $materiel['id'],
                'quantite'               => 1,
                'proprietaire_entite_id' => $materiel['proprietaire_entite_id'],
            ]);
        }

        return $ids;
    }

    private function lot(array $article, string $type, array $saisie): int
    {
        $numero = trim($saisie['numero_lot'] ?? '');
        if ($numero === '') {
            throw new DomainException('Le numéro de lot est obligatoire pour cet article.');
        }

        $lots = new LotModel();
        $lot  = $lots->where(['article_id' => $article['id'], 'numero_lot' => $numero])->first();
        if ($lot !== null) {
            return $lot['id'];
        }
        if ($type !== 'entree') {
            throw new DomainException("Lot inconnu : {$numero}.");
        }

        $peremption = trim($saisie['date_peremption'] ?? '');

        return (int) $lots->insert(['article_id' => $article['id'], 'numero_lot' => $numero, 'date_peremption' => $peremption === '' ? null : $peremption]);
    }

    private function emplacement(string $valeur, bool $attendu, string $role): ?int
    {
        if (! $attendu) {
            return null;
        }
        if (! ctype_digit($valeur)) {
            throw new DomainException("L'emplacement {$role} est obligatoire pour ce type de mouvement.");
        }

        return (int) $valeur;
    }

    private function quantite(string $valeur): string
    {
        $valeur = str_replace([',', ' '], ['.', ''], trim($valeur));
        if (! is_numeric($valeur) || (float) $valeur <= 0) {
            throw new DomainException('La quantité doit être un nombre positif.');
        }

        return $valeur;
    }

    /**
     * Propriétaire choisi, sinon l'entité qui détient l'emplacement concerné.
     */
    private function proprietaire(array $saisie, ?int $emplacement): int
    {
        if (ctype_digit((string) ($saisie['proprietaire'] ?? ''))) {
            return (int) $saisie['proprietaire'];
        }

        $ligne = $this->db->query('SELECT emplacement_entite(?) AS entite', [$emplacement])->getRow();

        return (int) $ligne->entite;
    }

    private function date(string $valeur): string
    {
        $valeur = trim($valeur);

        return $valeur === '' ? date('Y-m-d H:i:s') : str_replace('T', ' ', $valeur);
    }

    private function inserer(array $donnees): int
    {
        $model = new MouvementModel();
        $id    = $model->insert($donnees);

        if ($id === false) {
            throw new DomainException(implode(' ', $model->errors()));
        }

        return (int) $id;
    }
}
