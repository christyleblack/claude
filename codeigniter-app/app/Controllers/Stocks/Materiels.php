<?php

namespace App\Controllers\Stocks;

use App\Libraries\JournalConsultations;
use App\Controllers\BaseController;
use App\Models\Stocks\MaterielModel;
use App\Models\Stocks\MouvementModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * Fiche d'un matériel sérialisé : emplacement et détenteur actuels, historique, état (STK-05).
 */
class Materiels extends BaseController
{
    public function show(int $id)
    {
        $materiel = (new MaterielModel())->fiche($id);

        if ($materiel === null) {
            throw PageNotFoundException::forPageNotFound('Matériel introuvable.');
        }

        JournalConsultations::siSensible('materiel_serialise', $id);

        return view('stocks/materiels/show', [
            'titrePage'  => $materiel['designation'] . ' n° ' . $materiel['numero_serie'],
            'materiel'   => $materiel,
            'historique' => (new MouvementModel())->journal()->where('m.materiel_id', $id)->orderBy('m.id')->get()->getResultArray(),
            'types'      => MouvementModel::TYPES,
            'etats'      => MaterielModel::ETATS,
            'peutGerer'  => service('droits')->peut('stocks.mouvementer', $materiel['proprietaire_entite_id']),
        ]);
    }

    public function etat(int $id)
    {
        $materiel = (new MaterielModel())->find($id);
        $etat     = (string) $this->request->getPost('etat');

        if ($materiel === null) {
            throw PageNotFoundException::forPageNotFound('Matériel introuvable.');
        }
        if (! service('droits')->peut('stocks.mouvementer', $materiel['proprietaire_entite_id'])) {
            return redirect()->to('stocks/materiels/' . $id)->with('erreur', 'Vous ne gérez pas ce matériel.');
        }
        // La réforme passe par un mouvement de réforme, qui sort le matériel du stock
        if (! in_array($etat, ['en_service', 'en_reparation', 'indisponible'], true)) {
            return redirect()->to('stocks/materiels/' . $id)->with('erreur', 'État invalide (la réforme se fait par un mouvement de réforme).');
        }

        try {
            (new MaterielModel())->update($id, ['etat' => $etat]);
        } catch (Throwable) {
            return redirect()->to('stocks/materiels/' . $id)->with('erreur', 'Changement d\'état refusé.');
        }

        return redirect()->to('stocks/materiels/' . $id)->with('message', 'État mis à jour (tracé dans le journal d\'audit).');
    }
}
