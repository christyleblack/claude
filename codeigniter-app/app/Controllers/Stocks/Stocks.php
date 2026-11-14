<?php

namespace App\Controllers\Stocks;

use App\Controllers\BaseController;
use App\Models\Organisation\EntiteModel;
use App\Models\Stocks\StockModel;

/**
 * Consultation du stock (STK-01) : par emplacement, par entité détentrice, total ;
 * filtrable sur une entité et ses subordonnées. Limitée au périmètre (cloisonnement en base).
 */
class Stocks extends BaseController
{
    public function index()
    {
        $vue     = $this->request->getGet('vue');
        $vue     = array_key_exists((string) $vue, StockModel::VUES) ? $vue : 'emplacement';
        $filtres = [
            'q'      => trim((string) $this->request->getGet('q')),
            'entite' => (string) $this->request->getGet('entite'),
        ];

        return view('stocks/index', [
            'titrePage' => 'Stocks',
            'vue'       => $vue,
            'filtres'   => $filtres,
            'lignes'    => (new StockModel())->consulter($vue, $filtres),
            'entites'   => (new EntiteModel())->options(),
        ]);
    }
}
