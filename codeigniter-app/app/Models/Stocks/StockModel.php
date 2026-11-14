<?php

namespace App\Models\Stocks;

use CodeIgniter\Model;

/**
 * Stock calculé par la base à partir des mouvements (RG-10) : lecture seule.
 * Consultation à trois niveaux (STK-01) : détail par emplacement, par entité détentrice,
 * total national ; filtrable sur une entité et ses subordonnées.
 */
class StockModel extends Model
{
    public const VUES = ['emplacement' => 'Par emplacement', 'entite' => 'Par entité détentrice', 'nationale' => 'Total (périmètre visible)'];

    protected $table = 'stock';

    public function consulter(string $vue, array $filtres): array
    {
        $requete = $this->db->table('stock s')
            ->join('article a', 'a.id = s.article_id')
            ->join('emplacement em', 'em.id = s.emplacement_id')
            ->join('site si', 'si.id = em.site_id')
            ->join('entite d', 'd.id = si.entite_id');

        if (ctype_digit((string) ($filtres['entite'] ?? ''))) {
            // L'entité choisie et ses subordonnées hiérarchiques
            $requete->where('d.chemin_hierarchique <@ (SELECT chemin_hierarchique FROM entite WHERE id = ' . (int) $filtres['entite'] . ')', null, false);
        }
        if (($filtres['q'] ?? '') !== '') {
            $requete->groupStart()
                ->like('a.designation', $filtres['q'], 'both', null, true)
                ->orLike('a.codification', $filtres['q'], 'both', null, true)
                ->groupEnd();
        }

        return match ($vue) {
            'nationale' => $requete
                ->select('a.id AS article_id, a.codification, a.designation, a.unite_gestion, sum(s.quantite) AS quantite, count(DISTINCT d.id) AS nb_entites')
                ->groupBy('a.id, a.codification, a.designation, a.unite_gestion')
                ->orderBy('a.designation')->get()->getResultArray(),
            'entite' => $requete
                ->select('d.id AS entite_id, d.nom AS detenteur, a.id AS article_id, a.codification, a.designation, a.unite_gestion, sum(s.quantite) AS quantite')
                ->groupBy('d.id, d.nom, a.id, a.codification, a.designation, a.unite_gestion')
                ->orderBy('d.nom')->orderBy('a.designation')->get()->getResultArray(),
            default => $requete
                ->select('s.*, a.codification, a.designation, a.unite_gestion, em.libelle AS emplacement, si.nom AS site, d.nom AS detenteur,
                          p.nom AS proprietaire, l.numero_lot, l.date_peremption, ms.numero_serie, ms.etat')
                ->join('entite p', 'p.id = s.proprietaire_entite_id', 'left')
                ->join('lot l', 'l.id = s.lot_id', 'left')
                ->join('materiel_serialise ms', 'ms.id = s.materiel_id', 'left')
                ->orderBy('d.nom')->orderBy('si.nom')->orderBy('em.libelle')->orderBy('a.designation')
                ->get()->getResultArray(),
        };
    }

    /**
     * Emplacements actifs visibles, libellés « entité › site › emplacement ».
     *
     * @return array<int, string>
     */
    public function emplacements(): array
    {
        $options = [];
        foreach ($this->db->query(
            'SELECT em.id, d.nom AS entite, si.nom AS site, em.code, em.libelle
             FROM emplacement em JOIN site si ON si.id = em.site_id JOIN entite d ON d.id = si.entite_id
             WHERE em.actif AND si.actif ORDER BY d.nom, si.nom, em.code',
        )->getResultArray() as $e) {
            $options[(int) $e['id']] = $e['entite'] . ' › ' . $e['site'] . ' › ' . $e['code'] . ' – ' . $e['libelle'];
        }

        return $options;
    }
}
