<?php

namespace App\Controllers\Stocks;

use App\Controllers\BaseController;
use App\Libraries\BonPdf;
use App\Models\Stocks\BonModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Bons de mouvement (STK-06) : liste et PDF à la demande.
 * Un bon n'est visible que si ses mouvements le sont (cloisonnement en base).
 */
class Bons extends BaseController
{
    private const PAR_PAGE = 50;

    public function index()
    {
        $type  = (string) $this->request->getGet('type');
        $page  = max(1, (int) $this->request->getGet('page'));
        $bons  = (new BonModel())->avecAuteur();

        if (array_key_exists($type, BonModel::TYPES)) {
            $bons->where('bon.type', $type);
        }
        $lignes = $bons->orderBy('bon.id', 'DESC')->findAll(self::PAR_PAGE + 1, ($page - 1) * self::PAR_PAGE);

        return view('stocks/bons/index', [
            'titrePage' => 'Bons',
            'bons'      => array_slice($lignes, 0, self::PAR_PAGE),
            'suivante'  => count($lignes) > self::PAR_PAGE,
            'page'      => $page,
            'type'      => $type,
        ]);
    }

    public function pdf(int $id)
    {
        $model = new BonModel();
        $bon   = $model->avecAuteur()->where('bon.id', $id)->first();

        if ($bon === null) {
            throw PageNotFoundException::forPageNotFound('Bon introuvable.');
        }

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $bon['numero'] . '.pdf"')
            ->setBody(BonPdf::rendre($bon, $model->lignes($id)));
    }
}
