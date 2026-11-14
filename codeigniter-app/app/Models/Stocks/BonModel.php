<?php

namespace App\Models\Stocks;

use App\Models\BaseModel;

/**
 * Bons de mouvement (STK-06). Numéro attribué par la base, continu par type et par année.
 * Insertion seule : un bon émis ne se modifie pas.
 */
class BonModel extends BaseModel
{
    public const TYPES = [
        'perception' => 'Bon de perception',
        'sortie'     => 'Bon de sortie',
        'transfert'  => 'Bordereau de transfert',
    ];

    /** Type de bon selon le type de mouvement ; null : pas de bon */
    public const POUR_MOUVEMENT = [
        'perception'    => 'perception',
        'sortie'        => 'sortie',
        'perte'         => 'sortie',
        'reforme'       => 'sortie',
        'transfert'     => 'transfert',
        'reintegration' => 'transfert',
    ];

    protected $table         = 'bon';
    protected $allowedFields = ['type', 'cree_par'];

    protected array $casts = ['id' => 'int'];

    /**
     * Crée le bon des mouvements d'une même saisie et renvoie son identifiant.
     *
     * @param list<int> $mouvements
     */
    public function emettre(string $type, array $mouvements): int
    {
        // cree_par est complété par la base avec l'utilisateur connecté
        $id = (int) $this->insert(['type' => $type, 'cree_par' => auth()->id()]);

        foreach ($mouvements as $mouvement) {
            $this->db->table('bon_mouvement')->insert(['bon_id' => $id, 'mouvement_id' => $mouvement]);
        }

        return $id;
    }

    /**
     * Bon d'un mouvement, s'il en a un.
     */
    public function duMouvement(int $mouvementId): ?array
    {
        return $this->db->table('bon b')->select('b.*')
            ->join('bon_mouvement bm', 'bm.bon_id = b.id')
            ->where('bm.mouvement_id', $mouvementId)
            ->get()->getRowArray();
    }

    /**
     * Lignes du bon : ses mouvements, avec libellés et annulation éventuelle.
     */
    public function lignes(int $bonId): array
    {
        return (new MouvementModel())->journal()
            ->select('ent_o.nom AS entite_origine, ent_d.nom AS entite_destination')
            ->join('entite ent_o', 'ent_o.id = so.entite_id', 'left')
            ->join('entite ent_d', 'ent_d.id = sd.entite_id', 'left')
            ->join('bon_mouvement bm', 'bm.mouvement_id = m.id')
            ->where('bm.bon_id', $bonId)
            ->orderBy('m.id')
            ->get()->getResultArray();
    }

    public function avecAuteur(): static
    {
        return $this->select('bon.*, u.username AS auteur, (SELECT count(*) FROM bon_mouvement bm WHERE bm.bon_id = bon.id) AS nb_lignes')
            ->join('users u', 'u.id = bon.cree_par', 'left');
    }
}
