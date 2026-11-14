<?php

namespace App\Models\Demandes;

use App\Models\BaseModel;
use CodeIgniter\Database\RawSql;

/**
 * Demandes de matériel (DEM-01 à DEM-04). L'application crée la demande et ses lignes en
 * brouillon ; soumission, validation, service, clôture et annulation passent par les
 * fonctions de la base, qui contrôlent statut, droits et séparation des tâches.
 */
class DemandeModel extends BaseModel
{
    public const STATUTS = [
        'brouillon'     => 'Brouillon',
        'soumise'       => 'Soumise',
        'en_validation' => 'En validation',
        'validee'       => 'Validée, à servir',
        'refusee'       => 'Refusée',
        'en_service'    => 'En cours de service',
        'servie'        => 'Servie',
        'cloturee'      => 'Clôturée',
        'annulee'       => 'Annulée',
    ];

    public const PRIORITES = ['normale' => 'Normale', 'urgente' => 'Urgente'];

    /**
     * Vues de la liste : ce qui concerne l'utilisateur, puis tout son périmètre.
     */
    public const VUES = [
        'mes'        => 'Mes demandes',
        'a_valider'  => 'À valider',
        'a_servir'   => 'À servir',
        'perimetre'  => 'Tout mon périmètre',
    ];

    protected $table         = 'demande';
    protected $useTimestamps = true;
    protected $allowedFields = ['entite_id', 'filiere_id', 'demandeur_id', 'objet', 'date_besoin', 'priorite'];

    protected array $casts = ['id' => 'int', 'entite_id' => 'int', 'filiere_id' => 'int', 'demandeur_id' => 'int'];

    protected $validationRules = [
        'entite_id'   => 'required|is_natural_no_zero',
        'filiere_id'  => 'required|is_natural_no_zero',
        'objet'       => 'required|max_length[200]',
        'date_besoin' => 'permit_empty|valid_date[Y-m-d]',
        'priorite'    => 'required|in_list[normale,urgente]',
    ];

    protected $validationMessages = [
        'entite_id'   => ['required' => 'L\'entité demandeuse est obligatoire.', 'is_natural_no_zero' => 'L\'entité demandeuse est obligatoire.'],
        'filiere_id'  => ['required' => 'La filière est obligatoire.', 'is_natural_no_zero' => 'La filière est obligatoire.'],
        'objet'       => ['required' => 'L\'objet de la demande est obligatoire.', 'max_length' => 'Objet trop long (200 caractères au plus).'],
        'date_besoin' => ['valid_date' => 'Date de besoin invalide.'],
    ];

    protected $beforeInsert = ['viderChampsVides'];
    protected $beforeUpdate = ['viderChampsVides'];

    protected function viderChampsVides(array $data): array
    {
        if (($data['data']['date_besoin'] ?? null) === '') {
            $data['data']['date_besoin'] = null;
        }

        return $data;
    }

    /**
     * Demandes visibles selon la vue choisie, avec libellés et étape en cours.
     */
    public function liste(string $vue, string $statut, int $moi): array
    {
        $requete = $this->db->table('demande d')
            ->select("d.*, entite_libelle(d.entite_id) AS entite, f.libelle AS filiere, entite_libelle(d.entite_service_id) AS entite_service, u.username AS demandeur,
                      (SELECT count(*) FROM ligne_demande l WHERE l.demande_id = d.id) AS nb_lignes,
                      (SELECT et.libelle || ' (' || entite_libelle(et.entite_id) || ')' FROM etape_demande et
                        WHERE et.demande_id = d.id AND et.statut = 'en_attente' ORDER BY et.ordre LIMIT 1) AS etape_en_cours")
            ->join('filiere f', 'f.id = d.filiere_id')
            ->join('users u', 'u.id = d.demandeur_id', 'left');

        match ($vue) {
            'a_valider' => $requete->whereIn('d.statut', ['soumise', 'en_validation'])
                ->where('d.demandeur_id <>', $moi)
                ->where(new RawSql("demande_droit({$moi}, 'demandes.valider',
                    (SELECT et.entite_id FROM etape_demande et WHERE et.demande_id = d.id AND et.statut = 'en_attente' ORDER BY et.ordre LIMIT 1),
                    d.filiere_id)")),
            'a_servir' => $requete->whereIn('d.statut', ['validee', 'en_service'])
                ->where(new RawSql("{$moi} = ANY (demande_detenteurs('stocks.mouvementer', d.entite_service_id, d.filiere_id))")),
            'mes'      => $requete->where('d.demandeur_id', $moi),
            default    => null,
        };

        if ($statut !== '' && isset(self::STATUTS[$statut])) {
            $requete->where('d.statut', $statut);
        }

        return $requete->orderBy("d.priorite = 'urgente'", 'DESC', false)->orderBy('d.id', 'DESC')->get()->getResultArray();
    }

    public function fiche(int $id): ?array
    {
        return $this->db->table('demande d')
            ->select('d.*, entite_libelle(d.entite_id) AS entite, f.libelle AS filiere, f.code AS filiere_code,
                      entite_libelle(d.entite_service_id) AS entite_service, u.username AS demandeur, c.libelle AS circuit')
            ->join('filiere f', 'f.id = d.filiere_id')
            ->join('users u', 'u.id = d.demandeur_id', 'left')
            ->join('circuit_validation c', 'c.id = d.circuit_id', 'left')
            ->where('d.id', $id)
            ->get()->getRowArray();
    }

    public function lignes(int $id): array
    {
        return $this->db->table('ligne_demande l')
            ->select('l.*, a.codification, a.designation, a.unite_gestion, a.mode_suivi, a.sensibilite')
            ->join('article a', 'a.id = l.article_id')
            ->where('l.demande_id', $id)->orderBy('a.codification')
            ->get()->getResultArray();
    }

    public function etapes(int $id): array
    {
        return $this->db->table('etape_demande et')
            ->select('et.*, entite_libelle(et.entite_id) AS entite, u.username AS decideur')
            ->join('users u', 'u.id = et.decideur_id', 'left')
            ->where('et.demande_id', $id)->orderBy('et.ordre')
            ->get()->getResultArray();
    }

    public function evenements(int $id): array
    {
        return $this->db->table('evenement_demande ev')
            ->select('ev.*, u.username AS auteur')
            ->join('users u', 'u.id = ev.auteur_id', 'left')
            ->where('ev.demande_id', $id)->orderBy('ev.id')
            ->get()->getResultArray();
    }

    public function services(int $id): array
    {
        return $this->db->table('service_demande s')
            ->select('s.*, a.codification, m.date_mouvement, u.username AS serveur')
            ->join('ligne_demande l', 'l.id = s.ligne_demande_id')
            ->join('article a', 'a.id = l.article_id')
            ->join('mouvement m', 'm.id = s.mouvement_id')
            ->join('users u', 'u.id = s.servi_par', 'left')
            ->where('l.demande_id', $id)->orderBy('s.servi_le')
            ->get()->getResultArray();
    }

    /**
     * L'utilisateur peut-il décider de l'étape en cours ?
     */
    public function peutDecider(array $demande, int $moi): bool
    {
        if (! in_array($demande['statut'], ['soumise', 'en_validation'], true) || (int) $demande['demandeur_id'] === $moi) {
            return false;
        }

        return (int) $this->db->query(
            "SELECT demande_droit(?, 'demandes.valider',
                (SELECT entite_id FROM etape_demande WHERE demande_id = ? AND statut = 'en_attente' ORDER BY ordre LIMIT 1), ?)::int AS ok",
            [$moi, $demande['id'], $demande['filiere_id']],
        )->getRow()->ok === 1;
    }

    /**
     * L'utilisateur peut-il servir la demande (gestionnaire de l'entité de service ou de ses dépôts) ?
     */
    public function peutServir(array $demande, int $moi): bool
    {
        if ($demande['entite_service_id'] === null) {
            return false;
        }

        return (int) $this->db->query(
            "SELECT (? = ANY (demande_detenteurs('stocks.mouvementer', ?, ?)))::int AS ok",
            [$moi, $demande['entite_service_id'], $demande['filiere_id']],
        )->getRow()->ok === 1;
    }

    /**
     * Emplacements de livraison de la demande, pour qui peut la servir (fonction de la base :
     * l'entité demandeuse est souvent hors du périmètre du dépôt qui sert).
     */
    public function emplacementsLivraison(int $demande): array
    {
        return array_column($this->db->query('SELECT id, libelle FROM demande_emplacements_livraison(?)', [$demande])->getResultArray(), 'libelle', 'id');
    }

    /**
     * Emplacements actifs d'une entité et de ses subordonnées (arbre hiérarchique), dans le
     * périmètre de l'utilisateur.
     */
    public function emplacementsDe(int $entite): array
    {
        $options = [];
        foreach ($this->db->query(
            'SELECT em.id, d.nom AS entite, em.code, em.libelle
             FROM emplacement em JOIN site si ON si.id = em.site_id JOIN entite d ON d.id = si.entite_id
             WHERE em.actif AND si.actif
               AND d.chemin_hierarchique <@ (SELECT chemin_hierarchique FROM entite WHERE id = ?)
             ORDER BY d.nom, em.code',
            [$entite],
        )->getResultArray() as $e) {
            $options[(int) $e['id']] = $e['entite'] . ' › ' . $e['code'] . ' – ' . $e['libelle'];
        }

        return $options;
    }
}
