<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

/**
 * Service d'une ligne de demande (RG-17) : une perception depuis l'entité de service vers
 * l'entité demandeuse, rattachée à la ligne dans la même transaction. Si la base refuse le
 * rattachement (quantité supérieure au reste, mauvaise entité…), la perception et son bon
 * sont annulés avec lui.
 */
class ServiceDemandes
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array<string, string> $saisie origine, destination, quantite, numero_lot, numeros_serie
     *
     * @return list<int> mouvements créés
     *
     * @throws DomainException message lisible pour l'utilisateur
     */
    public function servir(int $ligneId, array $saisie): array
    {
        $ligne = $this->db->query(
            'SELECT l.id, a.codification, d.numero FROM ligne_demande l
             JOIN article a ON a.id = l.article_id JOIN demande d ON d.id = l.demande_id WHERE l.id = ?',
            [$ligneId],
        )->getRow();

        if ($ligne === null) {
            throw new DomainException('Ligne de demande introuvable.');
        }

        $this->db->transException(true)->transBegin();

        try {
            // Transaction imbriquée : la perception n'est validée qu'avec son rattachement
            $mouvements = (new SaisieMouvements($this->db))->enregistrer([
                'type'          => 'perception',
                'codification'  => $ligne->codification,
                'origine'       => $saisie['origine'] ?? '',
                'destination'   => $saisie['destination'] ?? '',
                'quantite'      => $saisie['quantite'] ?? '',
                'numero_lot'    => $saisie['numero_lot'] ?? '',
                'numeros_serie' => $saisie['numeros_serie'] ?? '',
                'motif'         => 'Service de la demande ' . $ligne->numero,
            ]);

            $this->db->transException(true);
            foreach ($mouvements as $mouvement) {
                $this->db->query('SELECT demande_servir(?, ?)', [$ligneId, $mouvement]);
            }
            $this->db->transCommit();

            return $mouvements;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e instanceof DomainException ? $e : new DomainException(self::message($e));
        } finally {
            $this->db->transException(false);
            $this->db->resetTransStatus();
        }
    }

    /**
     * Message lisible d'un refus de la base.
     */
    public static function message(Throwable $e): string
    {
        $texte = $e->getMessage();

        return match (true) {
            (bool) preg_match('/(Demande refusée|Mouvement refusé|Stock insuffisant)[^\n]*/u', $texte, $m) => preg_replace('/\s+CONTEXT:.*$/s', '', $m[0]),
            str_contains($texte, 'ligne_article_unique') => 'Cet article figure déjà dans la demande : modifiez sa quantité.',
            str_contains($texte, 'row-level security'), str_contains($texte, 'permission denied') => 'Vous n\'avez pas le droit de faire cette opération.',
            default => 'Opération refusée par la base de données.',
        };
    }
}
