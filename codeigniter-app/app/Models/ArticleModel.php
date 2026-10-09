<?php

namespace App\Models;

use CodeIgniter\Model;

class ArticleModel extends Model
{
    protected $table         = 'articles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['titre', 'contenu'];
    protected $useTimestamps = true;
}
