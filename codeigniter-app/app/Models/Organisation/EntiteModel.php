<?php

namespace App\Models\Organisation;

use CodeIgniter\DataCaster\Cast\BooleanCast;
use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Entités de l'organisation et leurs deux arbres (RG-01).
 *
 * Les colonnes chemin_hierarchique, chemin_logistique et force_id sont calculées
 * par la base (voir la migration CreerOrganisation) : elles ne figurent pas dans
 * $allowedFields et ne peuvent pas être saisies.
 */
class EntiteModel extends Model
{
    public const AXES = ['hierarchique', 'logistique'];

    protected $table         = 'entite';
    protected $useTimestamps = true;

    // PostgreSQL renvoie les booléens en 't' / 'f'
    protected array $casts = [
        'id'                         => 'int',
        'type_entite_id'             => 'int',
        'force_id'                   => '?int',
        'parent_hierarchique_id'     => '?int',
        'parent_logistique_id'       => '?int',
        'signature_nominative'       => 'boolean',
        'inventaire_contradictoire'  => 'boolean',
        'restitution_fin_engagement' => 'boolean',
        'actif'                      => 'boolean',
    ];
    protected $allowedFields = [
        'code', 'nom', 'type_entite_id', 'parent_hierarchique_id', 'parent_logistique_id',
        'signature_nominative', 'inventaire_contradictoire', 'restitution_fin_engagement', 'actif',
    ];

    protected $validationRules = [
        'id'                     => 'permit_empty|is_natural_no_zero',
        'code'                   => 'required|max_length[30]|regex_match[/^[A-Z0-9_-]+$/]|is_unique[entite.code,id,{id}]',
        'nom'                    => 'required|max_length[200]',
        'type_entite_id'         => 'required|is_not_unique[type_entite.id]',
        'parent_hierarchique_id' => 'permit_empty|is_not_unique[entite.id]',
        'parent_logistique_id'   => 'permit_empty|is_not_unique[entite.id]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres, « _ » et « - ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'nom'                    => ['required' => 'Le nom est obligatoire.'],
        'type_entite_id'         => ['required' => 'Le type est obligatoire.', 'is_not_unique' => 'Type inconnu.'],
        'parent_hierarchique_id' => ['is_not_unique' => 'Parent hiérarchique inconnu.'],
        'parent_logistique_id'   => ['is_not_unique' => 'Parent logistique inconnu.'],
    ];

    protected $beforeInsert = ['viderParentsVides'];
    protected $beforeUpdate = ['viderParentsVides'];

    /**
     * Un parent non renseigné dans un formulaire arrive en chaîne vide : il devient NULL.
     */
    protected function viderParentsVides(array $data): array
    {
        foreach (['parent_hierarchique_id', 'parent_logistique_id'] as $champ) {
            if (array_key_exists($champ, $data['data']) && $data['data'][$champ] === '') {
                $data['data'][$champ] = null;
            }
        }

        return $data;
    }

    private static function colonneChemin(string $axe): string
    {
        if (! in_array($axe, self::AXES, true)) {
            throw new InvalidArgumentException("Axe inconnu : {$axe}");
        }

        return 'chemin_' . $axe;
    }

    /**
     * Entités avec type et force, sous forme d'arbre aplati (ordre de lecture,
     * frères triés par nom) ; chaque ligne reçoit sa « profondeur ».
     */
    public function arbre(string $axe): array
    {
        $parent = 'parent_' . $axe . '_id';
        self::colonneChemin($axe);

        $lignes = $this->db->table('entite e')
            ->select('e.*, t.libelle AS type_libelle, f.code AS force_code')
            ->join('type_entite t', 't.id = e.type_entite_id')
            ->join('force f', 'f.id = e.force_id', 'left')
            ->orderBy('e.nom')
            ->get()->getResultArray();

        $enfants = [];
        foreach ($lignes as $ligne) {
            $ligne['actif'] = BooleanCast::get($ligne['actif']);
            $enfants[$ligne[$parent] ?? 0][] = $ligne;
        }

        $resultat = [];
        $parcourir = static function (int $idParent, int $profondeur) use (&$parcourir, &$resultat, $enfants): void {
            foreach ($enfants[$idParent] ?? [] as $ligne) {
                $ligne['profondeur'] = $profondeur;
                $resultat[]          = $ligne;
                $parcourir((int) $ligne['id'], $profondeur + 1);
            }
        };
        $parcourir(0, 0);

        return $resultat;
    }

    /**
     * Identifiants de l'entité et de toutes ses subordonnées sur un axe
     * (périmètre d'une affectation, règle 4.2-3).
     *
     * @return list<int>
     */
    public function sousArbre(int $id, string $axe): array
    {
        $colonne = self::colonneChemin($axe);

        $lignes = $this->db->query(
            "SELECT e.id FROM entite e
             WHERE e.{$colonne} <@ (SELECT {$colonne} FROM entite WHERE id = ?)
             ORDER BY e.id",
            [$id],
        )->getResultArray();

        return array_map('intval', array_column($lignes, 'id'));
    }

    /**
     * Ancêtres de l'entité sur un axe, de la racine au parent direct.
     */
    public function ancetres(int $id, string $axe): array
    {
        $colonne = self::colonneChemin($axe);

        return $this->db->query(
            "SELECT a.id, a.code, a.nom FROM entite a, entite e
             WHERE e.id = ? AND a.{$colonne} @> e.{$colonne} AND a.id <> e.id
             ORDER BY nlevel(a.{$colonne})",
            [$id],
        )->getResultArray();
    }

    /**
     * Subordonnées directes sur un axe.
     */
    public function enfants(int $id, string $axe): array
    {
        self::colonneChemin($axe);

        return $this->where('parent_' . $axe . '_id', $id)->orderBy('nom')->findAll();
    }

    /**
     * @return array<int, string> identifiant => « CODE – nom », entités actives
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->where('actif', true)->orderBy('nom')->findAll() as $e) {
            $options[(int) $e['id']] = $e['code'] . ' – ' . $e['nom'];
        }

        return $options;
    }
}
