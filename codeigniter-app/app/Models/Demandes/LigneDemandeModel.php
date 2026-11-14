<?php

namespace App\Models\Demandes;

use App\Models\BaseModel;

/**
 * Lignes d'une demande (RG-15) : modifiables seulement en brouillon, d'un seul article
 * de la filière de la demande (RG-16), contrôles faits par la base.
 */
class LigneDemandeModel extends BaseModel
{
    protected $table         = 'ligne_demande';
    protected $allowedFields = ['demande_id', 'article_id', 'quantite', 'observation'];

    protected $validationRules = [
        'quantite' => 'required|decimal|greater_than[0]',
    ];

    protected $validationMessages = [
        'quantite' => [
            'required'     => 'La quantité est obligatoire.',
            'decimal'      => 'Quantité invalide.',
            'greater_than' => 'La quantité doit être positive.',
        ],
    ];
}
