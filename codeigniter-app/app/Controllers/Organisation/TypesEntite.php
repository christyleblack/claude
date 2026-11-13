<?php

namespace App\Controllers\Organisation;

use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Types d'entités : ajouter un niveau d'organisation est un paramétrage (section 5, évolutivité).
 */
class TypesEntite extends OrganisationController
{
    private TypeEntiteModel $types;

    public function __construct()
    {
        $this->types = new TypeEntiteModel();
    }

    public function index()
    {
        return view('organisation/types/index', [
            'titrePage' => 'Types d\'entités',
            'types'     => $this->types->orderBy('libelle')->findAll(),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouveau type d\'entité', [], site_url('organisation/types'));
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->types, fn () => $this->types->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/types')->with('message', 'Type d\'entité créé.');
    }

    public function edit(int $id)
    {
        $type = $this->trouver($this->types, $id);

        return $this->formulaire('Modifier : ' . $type['libelle'], $type, site_url('organisation/types/' . $id));
    }

    public function update(int $id)
    {
        $this->trouver($this->types, $id);

        $resultat = $this->enregistrer($this->types, fn () => $this->types->update($id, $this->donnees() + ['id' => $id]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/types')->with('message', 'Type d\'entité modifié.');
    }

    private function donnees(): array
    {
        return [
            'code'         => strtoupper($this->champ('code')),
            'libelle'      => $this->champ('libelle'),
            'est_stockeur' => $this->case('est_stockeur'),
        ];
    }

    private function formulaire(string $titre, array $type, string $action)
    {
        return view('organisation/types/form', ['titrePage' => $titre, 'type' => $type, 'action' => $action]);
    }
}
