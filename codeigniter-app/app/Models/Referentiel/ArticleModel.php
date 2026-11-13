<?php

namespace App\Models\Referentiel;

use App\Models\BaseModel;

/**
 * Articles génériques nationaux (REF-01, REF-03, REF-04, REF-05).
 * Un article n'est jamais supprimé : il se désactive.
 */
class ArticleModel extends BaseModel
{
    public const SENSIBILITES = [
        'courant'  => 'Matériel courant',
        'sensible' => 'Matériel sensible',
        'armement' => 'Armement',
        'munition' => 'Munition',
    ];

    protected $table         = 'article';
    protected $useTimestamps = true;
    protected $allowedFields = ['codification', 'designation', 'unite_gestion', 'filiere_id', 'categorie_id', 'mode_suivi', 'sensibilite', 'actif'];

    protected array $casts = ['id' => 'int', 'filiere_id' => 'int', 'categorie_id' => 'int', 'actif' => 'boolean'];

    protected $validationRules = [
        'id'            => 'permit_empty|is_natural_no_zero',
        'codification'  => 'required|max_length[40]|regex_match[/^[A-Z0-9][A-Z0-9.\/-]*$/]|is_unique[article.codification,id,{id}]',
        'designation'   => 'required|max_length[250]',
        'unite_gestion' => 'required|is_not_unique[unite.code]',
        'filiere_id'    => 'required|is_not_unique[filiere.id]',
        'categorie_id'  => 'required|is_not_unique[categorie.id]',
        'mode_suivi'    => 'required|in_list[quantite,lot,serie]',
        'sensibilite'   => 'required|in_list[courant,sensible,armement,munition]',
    ];

    protected $validationMessages = [
        'codification' => [
            'required'    => 'La codification est obligatoire.',
            'regex_match' => 'La codification ne peut contenir que des majuscules, des chiffres, « . », « / » et « - ».',
            'is_unique'   => 'Cette codification est déjà utilisée.',
        ],
        'designation'   => ['required' => 'La désignation est obligatoire.'],
        'unite_gestion' => ['required' => 'L\'unité de gestion est obligatoire.', 'is_not_unique' => 'Unité inconnue.'],
        'categorie_id'  => ['required' => 'La catégorie est obligatoire.', 'is_not_unique' => 'Catégorie inconnue.'],
        'mode_suivi'    => ['required' => 'Le mode de suivi est obligatoire.', 'in_list' => 'Mode de suivi inconnu.'],
        'sensibilite'   => ['required' => 'La sensibilité est obligatoire.', 'in_list' => 'Sensibilité inconnue.'],
    ];

    /**
     * Recherche filtrée et paginée ; renvoie [lignes, il y a une page suivante].
     *
     * @param array{q?: string, filiere?: string, categorie?: string, mode?: string, sensibilite?: string, inactifs?: bool} $filtres
     */
    public function rechercher(array $filtres, int $page, int $parPage): array
    {
        $requete = $this->db->table('article a')
            ->select('a.*, f.libelle AS filiere, c.libelle AS categorie')
            ->join('filiere f', 'f.id = a.filiere_id')
            ->join('categorie c', 'c.id = a.categorie_id');

        if (($filtres['q'] ?? '') !== '') {
            $requete->groupStart()
                ->like('a.designation', $filtres['q'], 'both', null, true)
                ->orLike('a.codification', $filtres['q'], 'both', null, true)
                ->groupEnd();
        }
        foreach (['filiere' => 'a.filiere_id', 'categorie' => 'a.categorie_id'] as $cle => $colonne) {
            if (ctype_digit((string) ($filtres[$cle] ?? ''))) {
                $requete->where($colonne, (int) $filtres[$cle]);
            }
        }
        if (in_array($filtres['mode'] ?? '', ['quantite', 'lot', 'serie'], true)) {
            $requete->where('a.mode_suivi', $filtres['mode']);
        }
        if (array_key_exists($filtres['sensibilite'] ?? '', self::SENSIBILITES)) {
            $requete->where('a.sensibilite', $filtres['sensibilite']);
        }
        if (empty($filtres['inactifs'])) {
            $requete->where('a.actif', true);
        }

        $lignes = $requete->orderBy('a.designation')->limit($parPage + 1, ($page - 1) * $parPage)->get()->getResultArray();

        return [array_slice($lignes, 0, $parPage), count($lignes) > $parPage];
    }

    /**
     * @return array<string, string> code => « libellé (code) »
     */
    public function unites(): array
    {
        $unites = [];
        foreach ($this->db->table('unite')->orderBy('nature')->orderBy('code')->get()->getResultArray() as $u) {
            $unites[$u['code']] = $u['libelle'] . ' (' . $u['code'] . ')';
        }

        return $unites;
    }
}
