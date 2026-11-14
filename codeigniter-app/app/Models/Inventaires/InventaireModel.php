<?php

namespace App\Models\Inventaires;

use App\Models\BaseModel;

/**
 * Inventaires (INV-01 à INV-03). L'application ne fait que planifier : lancement,
 * comptage, signature, validation et annulation passent par les fonctions de la base.
 */
class InventaireModel extends BaseModel
{
    public const TYPES = [
        'complet'        => 'Inventaire complet',
        'tournant'       => 'Inventaire tournant',
        'contradictoire' => 'Inventaire contradictoire (passation de responsabilité)',
    ];

    public const STATUTS = [
        'planifie' => 'Planifié',
        'en_cours' => 'En cours de comptage',
        'compte'   => 'Compté, à valider',
        'valide'   => 'Validé',
        'annule'   => 'Annulé',
    ];

    protected $table         = 'inventaire';
    protected $useTimestamps = true;
    protected $allowedFields = ['type', 'entite_id', 'emplacement_id', 'date_prevue', 'compteur_id', 'cedant_id', 'prenant_id', 'observations', 'cree_par'];

    protected array $casts = ['id' => 'int', 'entite_id' => 'int', 'compteur_id' => 'int'];

    protected $validationRules = [
        'type'        => 'required|in_list[complet,tournant,contradictoire]',
        'entite_id'   => 'required|is_natural_no_zero',
        'date_prevue' => 'required|valid_date[Y-m-d]',
        'compteur_id' => 'required|is_not_unique[users.id]',
    ];

    protected $validationMessages = [
        'type'        => ['required' => 'Le type est obligatoire.'],
        'entite_id'   => ['required' => 'L\'entité inventoriée est obligatoire.', 'is_natural_no_zero' => 'L\'entité inventoriée est obligatoire.'],
        'date_prevue' => ['required' => 'La date prévue est obligatoire.', 'valid_date' => 'Date prévue invalide.'],
        'compteur_id' => ['required' => 'Le compteur est obligatoire.', 'is_not_unique' => 'Compteur inconnu.'],
    ];

    protected $beforeInsert = ['viderChampsVides'];

    protected function viderChampsVides(array $data): array
    {
        foreach (['emplacement_id', 'cedant_id', 'prenant_id', 'observations'] as $champ) {
            if (($data['data'][$champ] ?? null) === '') {
                $data['data'][$champ] = null;
            }
        }

        return $data;
    }

    /**
     * Inventaires visibles, avec libellés.
     */
    public function liste(?string $statut): array
    {
        $requete = $this->db->table('inventaire i')
            ->select('i.*, e.nom AS entite, em.libelle AS emplacement, c.username AS compteur,
                      (SELECT count(*) FROM ligne_inventaire l WHERE l.inventaire_id = i.id) AS nb_lignes,
                      (SELECT count(*) FROM ligne_inventaire l WHERE l.inventaire_id = i.id AND l.ecart <> 0) AS nb_ecarts')
            ->join('entite e', 'e.id = i.entite_id', 'left')
            ->join('emplacement em', 'em.id = i.emplacement_id', 'left')
            ->join('users c', 'c.id = i.compteur_id', 'left');

        if (array_key_exists((string) $statut, self::STATUTS)) {
            $requete->where('i.statut', $statut);
        }

        return $requete->orderBy('i.id', 'DESC')->get()->getResultArray();
    }

    public function fiche(int $id): ?array
    {
        return $this->db->table('inventaire i')
            ->select('i.*, e.nom AS entite, em.libelle AS emplacement, c.username AS compteur, v.username AS valideur,
                      ce.username AS cedant, pr.username AS prenant, cr.username AS createur')
            ->join('entite e', 'e.id = i.entite_id', 'left')
            ->join('emplacement em', 'em.id = i.emplacement_id', 'left')
            ->join('users c', 'c.id = i.compteur_id', 'left')
            ->join('users v', 'v.id = i.valideur_id', 'left')
            ->join('users ce', 'ce.id = i.cedant_id', 'left')
            ->join('users pr', 'pr.id = i.prenant_id', 'left')
            ->join('users cr', 'cr.id = i.cree_par', 'left')
            ->where('i.id', $id)
            ->get()->getRowArray();
    }

    public function lignes(int $id): array
    {
        return $this->db->table('ligne_inventaire l')
            ->select('l.*, a.codification, a.designation, a.unite_gestion, lo.numero_lot, ms.numero_serie, em.code AS emplacement_code, em.libelle AS emplacement')
            ->join('article a', 'a.id = l.article_id')
            ->join('lot lo', 'lo.id = l.lot_id', 'left')
            ->join('materiel_serialise ms', 'ms.id = l.materiel_id', 'left')
            ->join('emplacement em', 'em.id = l.emplacement_id', 'left')
            ->where('l.inventaire_id', $id)
            ->orderBy('em.code')->orderBy('a.designation')->orderBy('ms.numero_serie')->orderBy('lo.numero_lot')
            ->get()->getResultArray();
    }

    public function signatures(int $id): array
    {
        return array_column($this->db->table('signature_inventaire s')
            ->select('s.qualite, s.horodatage, u.username')
            ->join('users u', 'u.id = s.utilisateur_id')
            ->where('s.inventaire_id', $id)
            ->get()->getResultArray(), null, 'qualite');
    }
}
