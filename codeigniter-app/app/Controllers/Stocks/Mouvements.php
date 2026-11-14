<?php

namespace App\Controllers\Stocks;

use App\Controllers\BaseController;
use App\Libraries\SaisieMouvements;
use App\Models\Organisation\EntiteModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Stocks\MouvementModel;
use App\Models\Stocks\StockModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;

/**
 * Journal des mouvements (STK-02, STK-03) : consultation, saisie, annulation.
 */
class Mouvements extends BaseController
{
    private const PAR_PAGE = 50;

    public function index()
    {
        $filtres = [
            'type' => (string) $this->request->getGet('type'),
            'q'    => trim((string) $this->request->getGet('q')),
        ];
        $page    = max(1, (int) $this->request->getGet('page'));
        $requete = (new MouvementModel())->journal();

        if (array_key_exists($filtres['type'], MouvementModel::TYPES)) {
            $requete->where('m.type', $filtres['type']);
        }
        if ($filtres['q'] !== '') {
            $requete->groupStart()
                ->like('a.designation', $filtres['q'], 'both', null, true)
                ->orLike('a.codification', $filtres['q'], 'both', null, true)
                ->orLike('ms.numero_serie', $filtres['q'], 'both', null, true)
                ->orLike('l.numero_lot', $filtres['q'], 'both', null, true)
                ->groupEnd();
        }
        $lignes = $requete->orderBy('m.id', 'DESC')->limit(self::PAR_PAGE + 1, ($page - 1) * self::PAR_PAGE)->get()->getResultArray();

        return view('stocks/mouvements/index', [
            'titrePage'   => 'Mouvements',
            'mouvements'  => array_slice($lignes, 0, self::PAR_PAGE),
            'suivante'    => count($lignes) > self::PAR_PAGE,
            'page'        => $page,
            'filtres'     => $filtres,
            'peutSaisir'  => service('droits')->peut('stocks.mouvementer'),
        ]);
    }

    public function show(int $id)
    {
        $mouvement = (new MouvementModel())->journal()->where('m.id', $id)->get()->getRowArray();

        if ($mouvement === null) {
            throw PageNotFoundException::forPageNotFound('Mouvement introuvable.');
        }

        $responsable = (int) db_connect()->query(
            'SELECT mouvement_entite_responsable(?, ?, ?, ?) AS e',
            [$mouvement['type'], $mouvement['emplacement_origine_id'], $mouvement['emplacement_destination_id'], $mouvement['mouvement_annule_id']],
        )->getRow()->e;

        return view('stocks/mouvements/show', [
            'titrePage'    => 'Mouvement n° ' . $id,
            'm'            => $mouvement,
            'types'        => MouvementModel::TYPES,
            'peutAnnuler'  => $mouvement['type'] !== 'annulation' && $mouvement['annule_par'] === null
                && service('droits')->peut('stocks.mouvementer', $responsable),
        ]);
    }

    public function new()
    {
        $type = (string) $this->request->getGet('type');

        return view('stocks/mouvements/form', [
            'titrePage'    => 'Nouveau mouvement',
            'type'         => in_array($type, MouvementModel::TYPES_SAISIS, true) ? $type : 'entree',
            'types'        => array_intersect_key(MouvementModel::TYPES, array_flip(MouvementModel::TYPES_SAISIS)),
            'emplacements' => (new StockModel())->emplacements(),
            'entites'      => (new EntiteModel())->options(),
            'articles'     => (new ArticleModel())->where('actif', true)->orderBy('codification')->findAll(),
        ]);
    }

    public function create()
    {
        try {
            $ids = (new SaisieMouvements(db_connect()))->enregistrer($this->request->getPost());
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('erreurs', [$e->getMessage()]);
        }

        $message = count($ids) === 1 ? 'Mouvement n° ' . $ids[0] . ' enregistré.' : count($ids) . ' mouvements enregistrés (un par numéro de série).';

        return redirect()->to(count($ids) === 1 ? 'stocks/mouvements/' . $ids[0] : 'stocks/mouvements')->with('message', $message);
    }

    public function annuler(int $id)
    {
        try {
            $annulation = (new SaisieMouvements(db_connect()))->annuler($id, (string) $this->request->getPost('motif'));
        } catch (DomainException $e) {
            return redirect()->to('stocks/mouvements/' . $id)->with('erreur', $e->getMessage());
        }

        return redirect()->to('stocks/mouvements/' . $annulation)->with('message', 'Mouvement n° ' . $id . ' annulé par le mouvement inverse n° ' . $annulation . '.');
    }
}
