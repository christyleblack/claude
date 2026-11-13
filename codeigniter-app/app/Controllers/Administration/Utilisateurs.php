<?php

namespace App\Controllers\Administration;

use App\Controllers\GestionController;
use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EntiteModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Utilisateurs et affectations (ADM-01, RG-04).
 * Affecter un rôle sur une entité exige la permission administration.utilisateurs sur cette entité.
 */
class Utilisateurs extends GestionController
{
    public function index()
    {
        $actives = [];
        foreach (db_connect()->query(
            'SELECT utilisateur_id, count(*) AS n FROM affectation
             WHERE revoquee_le IS NULL AND date_debut <= current_date AND (date_fin IS NULL OR date_fin >= current_date)
             GROUP BY utilisateur_id',
        )->getResultArray() as $ligne) {
            $actives[(int) $ligne['utilisateur_id']] = (int) $ligne['n'];
        }

        return view('administration/utilisateurs/index', [
            'titrePage'    => 'Utilisateurs',
            'utilisateurs' => model(UserModel::class)->orderBy('username')->findAll(),
            'actives'      => $actives,
        ]);
    }

    public function show(int $id)
    {
        $utilisateur = model(UserModel::class)->findById($id);

        if ($utilisateur === null) {
            throw PageNotFoundException::forPageNotFound('Utilisateur introuvable.');
        }

        return view('administration/utilisateurs/show', [
            'titrePage'    => $utilisateur->username,
            'utilisateur'  => $utilisateur,
            'affectations' => (new AffectationModel())->deUtilisateur($id),
            'roles'        => (new RoleModel())->options(),
            'entites'      => (new EntiteModel())->options(),
        ]);
    }

    public function affecter(int $id)
    {
        if (model(UserModel::class)->findById($id) === null) {
            throw PageNotFoundException::forPageNotFound('Utilisateur introuvable.');
        }

        $entiteId = (int) $this->champ('entite_id');
        $role     = (new RoleModel())->find((int) $this->champ('role_id'));

        // Le périmètre de l'administrateur doit couvrir l'entité ; un rôle national exige un administrateur national
        $nationale = $role !== null && $role['portee'] === 'nationale';
        if (! service('droits')->peut('administration.utilisateurs', $entiteId ?: null, $nationale)) {
            return redirect()->back()->withInput()->with('erreurs', ['Cette affectation sort de votre périmètre d\'administration.']);
        }

        $model    = new AffectationModel();
        $resultat = $this->enregistrer($model, fn () => $model->insert([
            'utilisateur_id' => $id,
            'role_id'        => $this->champ('role_id'),
            'entite_id'      => $entiteId,
            'date_debut'     => $this->champ('date_debut'),
            'date_fin'       => $this->champ('date_fin'),
            'cree_par'       => auth()->id(),
        ]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('administration/utilisateurs/' . $id)->with('message', 'Affectation enregistrée.');
    }

    public function revoquer(int $affectationId)
    {
        $model       = new AffectationModel();
        $affectation = $this->trouver($model, $affectationId);

        if (! service('droits')->peut('administration.utilisateurs', $affectation['entite_id'])) {
            return redirect()->back()->with('erreur', 'Cette affectation sort de votre périmètre d\'administration.');
        }

        if ($affectation['revoquee_le'] === null) {
            $model->update($affectationId, ['revoquee_le' => date('Y-m-d H:i:s'), 'revoquee_par' => auth()->id()]);
        }

        return redirect()->to('administration/utilisateurs/' . $affectation['utilisateur_id'])->with('message', 'Affectation révoquée.');
    }
}
