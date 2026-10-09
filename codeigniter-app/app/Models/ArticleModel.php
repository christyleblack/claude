<?php

namespace App\Models;

use CodeIgniter\Model;

class ArticleModel extends Model
{
    protected $table         = 'articles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['titre', 'contenu'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'titre'   => 'required|min_length[3]|max_length[255]',
        'contenu' => 'permit_empty|max_length[10000]',
    ];

    protected $validationMessages = [
        'titre' => [
            'required'   => 'Le titre est obligatoire.',
            'min_length' => 'Le titre doit contenir au moins 3 caractères.',
            'max_length' => 'Le titre ne doit pas dépasser 255 caractères.',
        ],
        'contenu' => [
            'max_length' => 'Le contenu ne doit pas dépasser 10 000 caractères.',
        ],
    ];
}
