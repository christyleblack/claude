<?php

namespace App\Models\Referentiel;

use App\Models\BaseModel;

/**
 * Filières : familles de matériel, chacune gérée par un service central (RG-05).
 */
class FiliereModel extends BaseModel
{
    protected $table         = 'filiere';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'libelle', 'service_gestionnaire_id', 'actif'];

    protected array $casts = ['id' => 'int', 'service_gestionnaire_id' => 'int', 'actif' => 'boolean'];

    protected $validationRules = [
        'id'                      => 'permit_empty|is_natural_no_zero',
        'code'                    => 'required|max_length[30]|regex_match[/^[A-Z0-9_]+$/]|is_unique[filiere.code,id,{id}]',
        'libelle'                 => 'required|max_length[150]',
        'service_gestionnaire_id' => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres et « _ ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'libelle'                 => ['required' => 'Le libellé est obligatoire.'],
        'service_gestionnaire_id' => ['required' => 'Le service gestionnaire est obligatoire.', 'is_natural_no_zero' => 'Le service gestionnaire est obligatoire.'],
    ];

    /**
     * @return array<int, string>
     */
    public function options(bool $activesSeulement = true): array
    {
        if ($activesSeulement) {
            $this->where('actif', true);
        }

        return array_column($this->orderBy('libelle')->findAll(), 'libelle', 'id');
    }
}
