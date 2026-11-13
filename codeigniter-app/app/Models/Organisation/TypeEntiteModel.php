<?php

namespace App\Models\Organisation;

use CodeIgniter\Model;

class TypeEntiteModel extends Model
{
    protected $table         = 'type_entite';
    protected $allowedFields = ['code', 'libelle', 'est_stockeur'];
    protected $useTimestamps = true;

    // PostgreSQL renvoie les booléens en 't' / 'f'
    protected array $casts = ['id' => 'int', 'est_stockeur' => 'boolean'];

    protected $validationRules = [
        'id'      => 'permit_empty|is_natural_no_zero',
        'code'    => 'required|max_length[30]|regex_match[/^[A-Z0-9_]+$/]|is_unique[type_entite.code,id,{id}]',
        'libelle' => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres et « _ ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'libelle' => ['required' => 'Le libellé est obligatoire.'],
    ];

    /**
     * @return array<int, string> identifiant => libellé
     */
    public function options(): array
    {
        return array_column($this->orderBy('libelle')->findAll(), 'libelle', 'id');
    }
}
