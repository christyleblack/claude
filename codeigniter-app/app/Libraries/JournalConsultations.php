<?php

namespace App\Libraries;

/**
 * Journal des consultations de données sensibles (cahier des charges, section 5,
 * « Traçabilité » : connexion, consultation de données sensibles, modification).
 *
 * Les modifications sont journalisées par la base (déclencheurs) et les connexions par les
 * événements de Shield ; ici, on trace l'affichage d'une fiche qui porte sur du matériel
 * sensible (armement, munitions, matériel sensible) : matériel sérialisé, mouvement, bon.
 * Le journal d'audit n'accepte que des ajouts.
 */
class JournalConsultations
{
    /**
     * Requêtes qui disent si l'objet porte sur un article sensible, par type d'objet.
     */
    private const SENSIBLE = [
        'materiel_serialise' => 'SELECT article_sensible(article_id) AS s FROM materiel_serialise WHERE id = ?',
        'mouvement'          => 'SELECT article_sensible(article_id) AS s FROM mouvement WHERE id = ?',
        'bon'                => 'SELECT coalesce(bool_or(article_sensible(m.article_id)), false) AS s
                                   FROM bon_mouvement bm JOIN mouvement m ON m.id = bm.mouvement_id WHERE bm.bon_id = ?',
    ];

    /**
     * Journalise la consultation si l'objet porte sur du matériel sensible.
     *
     * @return bool vrai si une consultation a été journalisée
     */
    public static function siSensible(string $objetType, int $id, string $vue = 'fiche'): bool
    {
        $db    = db_connect();
        $ligne = $db->query(self::SENSIBLE[$objetType], [$id])->getRow();

        if ($ligne === null || ! in_array($ligne->s, [true, 't', 1, '1'], true)) {
            return false;
        }

        $db->table('journal_audit')->insert([
            'utilisateur_id' => auth()->id(),
            'action'         => 'CONSULTATION',
            'objet_type'     => $objetType,
            'objet_id'       => $id,
            'details'        => json_encode(['vue' => $vue], JSON_UNESCAPED_UNICODE),
            'adresse_ip'     => service('request')->getIPAddress(),
        ]);

        return true;
    }
}
