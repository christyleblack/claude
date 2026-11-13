<?php

namespace App\Controllers\Administration;

use App\Controllers\GestionController;
use App\Models\Droits\RoleModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Rôles et permissions (RBAC), paramétrables sans développement (ADM-02).
 */
class Roles extends GestionController
{
    private RoleModel $roles;

    public function __construct()
    {
        $this->roles = new RoleModel();
    }

    public function index()
    {
        $roles = $this->roles->orderBy('libelle')->findAll();
        foreach ($roles as &$role) {
            $role['permissions'] = $this->roles->permissions($role['id']);
        }

        return view('administration/roles/index', [
            'titrePage'   => 'Rôles',
            'roles'       => $roles,
            'permissions' => $this->roles->toutesPermissions(),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouveau rôle', ['actif' => true, 'portee' => 'entite', 'permissions' => []], site_url('administration/roles'));
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->roles, fn () => $this->roles->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        $this->roles->definirPermissions((int) $resultat, $this->permissionsSaisies());

        return redirect()->to('administration/roles')->with('message', 'Rôle créé.');
    }

    public function edit(int $id)
    {
        $role                = $this->trouver($this->roles, $id);
        $role['permissions'] = $this->roles->permissions($id);

        return $this->formulaire('Modifier : ' . $role['libelle'], $role, site_url('administration/roles/' . $id));
    }

    public function update(int $id)
    {
        $this->trouver($this->roles, $id);

        $resultat = $this->enregistrer($this->roles, fn () => $this->roles->update($id, $this->donnees() + ['id' => $id]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        $this->roles->definirPermissions($id, $this->permissionsSaisies());

        return redirect()->to('administration/roles')->with('message', 'Rôle modifié.');
    }

    private function donnees(): array
    {
        return [
            'code'    => strtoupper($this->champ('code')),
            'libelle' => $this->champ('libelle'),
            'portee'  => $this->champ('portee'),
            'actif'   => $this->case('actif'),
        ];
    }

    /**
     * Permissions cochées, limitées aux permissions connues.
     *
     * @return list<string>
     */
    private function permissionsSaisies(): array
    {
        $saisies = (array) ($this->request->getPost('permissions') ?? []);

        return array_values(array_intersect(array_keys($this->roles->toutesPermissions()), $saisies));
    }

    private function formulaire(string $titre, array $role, string $action)
    {
        return view('administration/roles/form', [
            'titrePage'   => $titre,
            'role'        => $role,
            'action'      => $action,
            'permissions' => $this->roles->toutesPermissions(),
        ]);
    }
}
