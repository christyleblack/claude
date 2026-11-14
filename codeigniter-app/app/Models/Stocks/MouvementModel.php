<?php

namespace App\Models\Stocks;

use App\Models\BaseModel;

/**
 * Journal des mouvements : insertion seule (RG-12). La base complète l'auteur, la date
 * de saisie et l'empreinte, met à jour le stock et refuse toute modification.
 */
class MouvementModel extends BaseModel
{
    public const TYPES = [
        'entree'         => 'Entrée',
        'sortie'         => 'Sortie',
        'transfert'      => 'Transfert',
        'perception'     => 'Perception',
        'reintegration'  => 'Réintégration',
        'perte'          => 'Perte',
        'reforme'        => 'Réforme',
        'regularisation' => 'Régularisation',
        'annulation'     => 'Annulation',
    ];

    /** Types saisis à la main ; la régularisation viendra des inventaires */
    public const TYPES_SAISIS = ['entree', 'sortie', 'transfert', 'perception', 'reintegration', 'perte', 'reforme'];

    /** Types avec origine et/ou destination */
    public const AVEC_ORIGINE     = ['sortie', 'transfert', 'perception', 'reintegration', 'perte', 'reforme'];
    public const AVEC_DESTINATION = ['entree', 'transfert', 'perception', 'reintegration'];

    protected $table         = 'mouvement';
    protected $allowedFields = [
        'type', 'article_id', 'lot_id', 'materiel_id', 'quantite', 'emplacement_origine_id',
        'emplacement_destination_id', 'proprietaire_entite_id', 'auteur_id', 'date_mouvement', 'motif', 'mouvement_annule_id',
    ];

    protected array $casts = ['id' => 'int', 'article_id' => 'int'];

    /**
     * Requête de base du journal, avec libellés (emplacements hors périmètre : nuls).
     */
    public function journal(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('mouvement m')
            ->select("m.*, a.codification, a.designation, a.unite_gestion, l.numero_lot, ms.numero_serie,
                      eo.libelle AS origine, so.nom AS site_origine, ed.libelle AS destination, sd.nom AS site_destination,
                      p.nom AS proprietaire, u.username AS auteur,
                      (SELECT x.id FROM mouvement x WHERE x.mouvement_annule_id = m.id) AS annule_par")
            ->join('article a', 'a.id = m.article_id')
            ->join('lot l', 'l.id = m.lot_id', 'left')
            ->join('materiel_serialise ms', 'ms.id = m.materiel_id', 'left')
            ->join('emplacement eo', 'eo.id = m.emplacement_origine_id', 'left')
            ->join('site so', 'so.id = eo.site_id', 'left')
            ->join('emplacement ed', 'ed.id = m.emplacement_destination_id', 'left')
            ->join('site sd', 'sd.id = ed.site_id', 'left')
            ->join('entite p', 'p.id = m.proprietaire_entite_id', 'left')
            ->join('users u', 'u.id = m.auteur_id', 'left');
    }
}
