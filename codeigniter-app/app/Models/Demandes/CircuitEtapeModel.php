<?php

namespace App\Models\Demandes;

use App\Models\BaseModel;

class CircuitEtapeModel extends BaseModel
{
    protected $table         = 'circuit_etape';
    protected $useTimestamps = true;
    protected $allowedFields = ['circuit_id', 'ordre', 'libelle', 'mode', 'niveau', 'entite_id', 'seuil_quantite'];

    protected $validationRules = [
        'ordre'          => 'required|is_natural_no_zero',
        'libelle'        => 'required|max_length[150]',
        'mode'           => 'required|in_list[logistique,filiere,entite]',
        'niveau'         => 'permit_empty|is_natural_no_zero',
        'seuil_quantite' => 'permit_empty|decimal|greater_than[0]',
    ];

    protected $validationMessages = [
        'ordre'          => ['required' => 'Le rang est obligatoire.', 'is_natural_no_zero' => 'Rang invalide.'],
        'libelle'        => ['required' => 'Le libellé est obligatoire.'],
        'niveau'         => ['is_natural_no_zero' => 'Le niveau est un entier positif (1 = parent logistique).'],
        'seuil_quantite' => ['decimal' => 'Seuil invalide.', 'greater_than' => 'Le seuil doit être positif.'],
    ];
}
