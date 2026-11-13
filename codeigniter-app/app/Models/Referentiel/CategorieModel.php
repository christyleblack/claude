<?php

namespace App\Models\Referentiel;

use App\Models\BaseModel;

/**
 * Catégories d'une filière, en arborescence. Une catégorie peut imposer
 * le mode de suivi de ses articles (RG-07) : la base le fait respecter.
 */
class CategorieModel extends BaseModel
{
    public const MODES_SUIVI = ['quantite' => 'En quantité (vrac, volume)', 'lot' => 'Par lot (n° de lot, péremption)', 'serie' => 'Par numéro de série'];

    protected $table         = 'categorie';
    protected $useTimestamps = true;
    protected $allowedFields = ['filiere_id', 'parent_id', 'code', 'libelle', 'mode_suivi_impose', 'actif'];

    protected array $casts = ['id' => 'int', 'filiere_id' => 'int', 'parent_id' => '?int', 'actif' => 'boolean'];

    protected $validationRules = [
        'id'                => 'permit_empty|is_natural_no_zero',
        'filiere_id'        => 'required|is_not_unique[filiere.id]',
        'code'              => 'required|max_length[30]|regex_match[/^[A-Z0-9_-]+$/]',
        'libelle'           => 'required|max_length[150]',
        'mode_suivi_impose' => 'permit_empty|in_list[quantite,lot,serie]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres, « _ » et « - ».',
        ],
        'libelle' => ['required' => 'Le libellé est obligatoire.'],
    ];

    protected $beforeInsert = ['viderChampsVides'];
    protected $beforeUpdate = ['viderChampsVides'];

    protected function viderChampsVides(array $data): array
    {
        foreach (['parent_id', 'mode_suivi_impose'] as $champ) {
            if (array_key_exists($champ, $data['data']) && $data['data'][$champ] === '') {
                $data['data'][$champ] = null;
            }
        }

        return $data;
    }

    /**
     * Catégories d'une filière en arbre aplati, avec leur profondeur.
     */
    public function arbreDeLaFiliere(int $filiereId): array
    {
        $lignes  = $this->where('filiere_id', $filiereId)->orderBy('libelle')->findAll();
        $enfants = [];
        foreach ($lignes as $ligne) {
            $enfants[$ligne['parent_id'] ?? 0][] = $ligne;
        }

        $resultat  = [];
        $parcourir = static function (int $idParent, int $profondeur) use (&$parcourir, &$resultat, $enfants): void {
            foreach ($enfants[$idParent] ?? [] as $ligne) {
                $ligne['profondeur'] = $profondeur;
                $resultat[]          = $ligne;
                $parcourir($ligne['id'], $profondeur + 1);
            }
        };
        $parcourir(0, 0);

        return $resultat;
    }

    /**
     * Options groupées par filière : [libellé de filière => [id => libellé indenté]].
     */
    public function optionsParFiliere(): array
    {
        $options = [];
        foreach ((new FiliereModel())->where('actif', true)->orderBy('libelle')->findAll() as $filiere) {
            foreach ($this->arbreDeLaFiliere($filiere['id']) as $c) {
                if ($c['actif']) {
                    $options[$filiere['libelle']][$c['id']] = str_repeat('— ', $c['profondeur']) . $c['libelle'];
                }
            }
        }

        return $options;
    }
}
