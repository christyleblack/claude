<?php

namespace App\Models\Organisation;

use App\Models\BaseModel;

/**
 * Forces et paramètres d'autonomie de leur direction du matériel (section 2.5).
 * Une force est rattachée à son entité racine dans l'arbre hiérarchique ;
 * toutes les entités subordonnées lui appartiennent automatiquement (RG-02).
 */
class ForceModel extends BaseModel
{
    protected $table         = 'force';
    protected $useTimestamps = true;

    // PostgreSQL renvoie les booléens en 't' / 'f'
    protected array $casts = ['id' => 'int', 'entite_racine_id' => 'int', 'validation_finale_locale' => 'boolean', 'achats_propres' => 'boolean'];
    protected $allowedFields = [
        'code', 'nom', 'entite_racine_id', 'validation_finale_locale', 'achats_propres',
        'seuil_quantite_dcma', 'seuil_montant_dcma',
    ];

    protected $validationRules = [
        'id'                  => 'permit_empty|is_natural_no_zero',
        'code'                => 'required|max_length[30]|regex_match[/^[A-Z0-9_-]+$/]|is_unique[force.code,id,{id}]',
        'nom'                 => 'required|max_length[200]',
        'entite_racine_id'    => 'required|is_not_unique[entite.id]|is_unique[force.entite_racine_id,id,{id}]',
        'seuil_quantite_dcma' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'seuil_montant_dcma'  => 'permit_empty|decimal|greater_than_equal_to[0]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres, « _ » et « - ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'nom'              => ['required' => 'Le nom est obligatoire.'],
        'entite_racine_id' => [
            'required'      => 'L\'entité racine est obligatoire.',
            'is_not_unique' => 'Entité inconnue.',
            'is_unique'     => 'Cette entité est déjà la racine d\'une autre force.',
        ],
        'seuil_quantite_dcma' => ['decimal' => 'Seuil de quantité invalide.', 'greater_than_equal_to' => 'Le seuil de quantité doit être positif.'],
        'seuil_montant_dcma'  => ['decimal' => 'Seuil de montant invalide.', 'greater_than_equal_to' => 'Le seuil de montant doit être positif.'],
    ];

    protected $beforeInsert = ['viderSeuilsVides'];
    protected $beforeUpdate = ['viderSeuilsVides'];

    protected function viderSeuilsVides(array $data): array
    {
        foreach (['seuil_quantite_dcma', 'seuil_montant_dcma'] as $champ) {
            if (array_key_exists($champ, $data['data']) && $data['data'][$champ] === '') {
                $data['data'][$champ] = null;
            }
        }

        return $data;
    }

    public function avecRacine(): static
    {
        return $this->select('force.*, e.code AS racine_code, e.nom AS racine_nom')
            ->join('entite e', 'e.id = force.entite_racine_id', 'left');
    }
}
