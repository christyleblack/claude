<?php

namespace App\Models\Organisation;

use App\Models\BaseModel;

/**
 * Emplacements de stockage d'un site, éventuellement imbriqués.
 * La base garantit qu'un emplacement n'est contenu que dans un emplacement
 * du même site, et refuse les cycles.
 */
class EmplacementModel extends BaseModel
{
    protected $table         = 'emplacement';
    protected $useTimestamps = true;

    // PostgreSQL renvoie les booléens en 't' / 'f'
    protected array $casts = ['id' => 'int', 'site_id' => 'int', 'parent_id' => '?int', 'actif' => 'boolean'];
    protected $allowedFields = ['site_id', 'parent_id', 'code', 'libelle', 'actif'];

    protected $validationRules = [
        'id'      => 'permit_empty|is_natural_no_zero',
        'site_id' => 'required|is_not_unique[site.id]',
        'code'    => 'required|max_length[30]|regex_match[/^[A-Z0-9_-]+$/]',
        'libelle' => 'required|max_length[200]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres, « _ » et « - ».',
        ],
        'libelle' => ['required' => 'Le libellé est obligatoire.'],
    ];

    protected $beforeInsert = ['viderParentVide'];
    protected $beforeUpdate = ['viderParentVide'];

    protected function viderParentVide(array $data): array
    {
        if (array_key_exists('parent_id', $data['data']) && $data['data']['parent_id'] === '') {
            $data['data']['parent_id'] = null;
        }

        return $data;
    }

    /**
     * Emplacements d'un site en arbre aplati, avec leur profondeur.
     */
    public function arbreDuSite(int $siteId): array
    {
        $lignes  = $this->where('site_id', $siteId)->orderBy('code')->findAll();
        $enfants = [];
        foreach ($lignes as $ligne) {
            $enfants[$ligne['parent_id'] ?? 0][] = $ligne;
        }

        $resultat  = [];
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
}
