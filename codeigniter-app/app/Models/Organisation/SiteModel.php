<?php

namespace App\Models\Organisation;

use App\Models\BaseModel;

class SiteModel extends BaseModel
{
    protected $table         = 'site';
    protected $useTimestamps = true;

    // PostgreSQL renvoie les booléens en 't' / 'f'
    protected array $casts = ['id' => 'int', 'entite_id' => 'int', 'actif' => 'boolean'];
    protected $allowedFields = ['code', 'nom', 'adresse', 'entite_id', 'actif'];

    protected $validationRules = [
        'id'        => 'permit_empty|is_natural_no_zero',
        'code'      => 'required|max_length[30]|regex_match[/^[A-Z0-9_-]+$/]|is_unique[site.code,id,{id}]',
        'nom'       => 'required|max_length[200]',
        'entite_id' => 'required|is_not_unique[entite.id]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres, « _ » et « - ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'nom'       => ['required' => 'Le nom est obligatoire.'],
        'entite_id' => ['required' => 'L\'entité est obligatoire.', 'is_not_unique' => 'Entité inconnue.'],
    ];
}
