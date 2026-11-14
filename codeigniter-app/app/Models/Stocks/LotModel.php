<?php

namespace App\Models\Stocks;

use App\Models\BaseModel;

/**
 * Lots (n° de lot, date de péremption) des articles suivis par lot (REF-04).
 */
class LotModel extends BaseModel
{
    protected $table         = 'lot';
    protected $useTimestamps = true;
    protected $allowedFields = ['article_id', 'numero_lot', 'date_peremption'];

    protected array $casts = ['id' => 'int', 'article_id' => 'int'];
}
