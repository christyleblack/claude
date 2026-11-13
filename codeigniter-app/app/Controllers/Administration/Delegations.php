<?php

namespace App\Controllers\Administration;

use App\Controllers\GestionController;
use App\Models\Droits\AffectationModel;
use App\Models\Droits\DelegationModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;

/**
 * « Mes droits » : affectations de l'utilisateur et délégations données ou reçues (règle 4.2-5).
 * Le titulaire d'une affectation peut la déléguer temporairement et révoquer sa délégation ;
 * un administrateur dont le périmètre couvre l'entité le peut aussi.
 */
class Delegations extends GestionController
{
    public function index()
    {
        $moi = (int) auth()->id();

        return view('administration/delegations/index', [
            'titrePage'    => 'Mes droits',
            'actives'      => service('droits')->affectationsActives(),
            'affectations' => (new AffectationModel())->deUtilisateur($moi),
            'delegations'  => (new DelegationModel())->concernant($moi),
        ]);
    }

    public function new()
    {
        $affectation = (new AffectationModel())->find((int) $this->request->getGet('affectation'));

        if ($affectation === null || ! $this->peutDeleguer($affectation)) {
            return redirect()->to('administration/mes-droits')->with('erreur', 'Vous ne pouvez déléguer que vos propres affectations.');
        }

        $delegataires = [];
        foreach (model(UserModel::class)->where('id !=', $affectation['utilisateur_id'])->orderBy('username')->findAll() as $u) {
            $delegataires[$u->id] = $u->username;
        }

        return view('administration/delegations/form', [
            'titrePage'    => 'Déléguer une affectation',
            'affectation'  => current(array_filter(
                (new AffectationModel())->deUtilisateur($affectation['utilisateur_id']),
                static fn ($a) => (int) $a['id'] === $affectation['id'],
            )),
            'delegataires' => $delegataires,
        ]);
    }

    public function create()
    {
        $affectation = (new AffectationModel())->find((int) $this->champ('affectation_id'));

        if ($affectation === null || ! $this->peutDeleguer($affectation)) {
            return redirect()->to('administration/mes-droits')->with('erreur', 'Vous ne pouvez déléguer que vos propres affectations.');
        }

        $model    = new DelegationModel();
        $resultat = $this->enregistrer($model, fn () => $model->insert([
            'affectation_id' => $affectation['id'],
            'delegataire_id' => $this->champ('delegataire_id'),
            'date_debut'     => $this->champ('date_debut'),
            'date_fin'       => $this->champ('date_fin'),
            'motif'          => $this->champ('motif'),
            'cree_par'       => auth()->id(),
        ]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('administration/mes-droits')->with('message', 'Délégation enregistrée.');
    }

    public function revoquer(int $id)
    {
        $model       = new DelegationModel();
        $delegation  = $this->trouver($model, $id);
        $affectation = (new AffectationModel())->find($delegation['affectation_id']);

        if (! $this->peutDeleguer($affectation)) {
            return redirect()->to('administration/mes-droits')->with('erreur', 'Seul le titulaire de l\'affectation ou un administrateur peut révoquer cette délégation.');
        }

        if ($delegation['revoquee_le'] === null) {
            $model->update($id, ['revoquee_le' => date('Y-m-d H:i:s'), 'revoquee_par' => auth()->id()]);
        }

        return redirect()->to('administration/mes-droits')->with('message', 'Délégation révoquée.');
    }

    /**
     * Le titulaire de l'affectation, ou un administrateur dont le périmètre couvre l'entité.
     * Une affectation reçue par délégation ne peut pas être redéléguée.
     */
    private function peutDeleguer(array $affectation): bool
    {
        return $affectation['utilisateur_id'] === (int) auth()->id()
            || service('droits')->peut('administration.utilisateurs', $affectation['entite_id']);
    }
}
