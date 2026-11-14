<?php

namespace App\Models\Stocks;

use App\Models\BaseModel;

/**
 * Matériels sérialisés : un exemplaire (une arme, un engin) avec son numéro de série,
 * son état (STK-05) et son propriétaire. Sa position découle de ses mouvements (RG-14).
 */
class MaterielModel extends BaseModel
{
    public const ETATS = [
        'en_service'    => 'En service',
        'en_reparation' => 'En réparation',
        'indisponible'  => 'Indisponible',
        'reforme'       => 'Réformé',
    ];

    protected $table         = 'materiel_serialise';
    protected $useTimestamps = true;
    protected $allowedFields = ['article_id', 'numero_serie', 'etat', 'proprietaire_entite_id'];

    protected array $casts = ['id' => 'int', 'article_id' => 'int', 'proprietaire_entite_id' => 'int'];

    protected $validationRules = [
        'etat' => 'permit_empty|in_list[en_service,en_reparation,indisponible,reforme]',
    ];

    /**
     * Fiche : article, propriétaire, emplacement et détenteur actuels (s'il est en stock).
     */
    public function fiche(int $id): ?array
    {
        return $this->db->query(
            "SELECT m.*, a.codification, a.designation, p.nom AS proprietaire,
                    s.emplacement_id, em.libelle AS emplacement, si.nom AS site, d.nom AS detenteur
             FROM materiel_serialise m
             JOIN article a ON a.id = m.article_id
             LEFT JOIN entite p ON p.id = m.proprietaire_entite_id
             LEFT JOIN stock s ON s.materiel_id = m.id
             LEFT JOIN emplacement em ON em.id = s.emplacement_id
             LEFT JOIN site si ON si.id = em.site_id
             LEFT JOIN entite d ON d.id = si.entite_id
             WHERE m.id = ?",
            [$id],
        )->getRowArray();
    }
}
