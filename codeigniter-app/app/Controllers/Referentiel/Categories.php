<?php

namespace App\Controllers\Referentiel;

use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Catégories d'une filière (arborescence, mode de suivi imposé).
 */
class Categories extends ReferentielController
{
    private CategorieModel $categories;

    public function __construct()
    {
        $this->categories = new CategorieModel();
    }

    public function index(int $filiereId)
    {
        $filiere = $this->trouver(new FiliereModel(), $filiereId);

        return view('referentiel/categories/index', [
            'titrePage'  => 'Catégories : ' . $filiere['libelle'],
            'filiere'    => $filiere,
            'categories' => $this->categories->arbreDeLaFiliere($filiereId),
            'peutGerer'  => $this->peutGerer($filiereId),
            'modes'      => CategorieModel::MODES_SUIVI,
        ]);
    }

    public function new()
    {
        $filiere = (new FiliereModel())->find((int) $this->request->getGet('filiere'));

        if ($filiere === null) {
            throw PageNotFoundException::forPageNotFound('Filière introuvable.');
        }
        if (! $this->peutGerer($filiere['id'])) {
            return redirect()->to('referentiel/filieres/' . $filiere['id'] . '/categories')->with('erreur', 'Vous ne gérez pas cette filière.');
        }

        return $this->formulaire('Nouvelle catégorie', $filiere, ['actif' => true, 'parent_id' => $this->request->getGet('parent')], site_url('referentiel/categories'));
    }

    public function create()
    {
        $filiereId = (int) $this->champ('filiere_id');

        if (! $this->peutGerer($filiereId)) {
            return $this->refuser('Vous ne gérez pas cette filière.');
        }

        $resultat = $this->enregistrer($this->categories, fn () => $this->categories->insert($this->donnees() + ['filiere_id' => (string) $filiereId]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/filieres/' . $filiereId . '/categories')->with('message', 'Catégorie créée.');
    }

    public function edit(int $id)
    {
        $categorie = $this->trouver($this->categories, $id);

        if (! $this->peutGerer($categorie['filiere_id'])) {
            return redirect()->to('referentiel/filieres/' . $categorie['filiere_id'] . '/categories')->with('erreur', 'Vous ne gérez pas cette filière.');
        }

        return $this->formulaire('Modifier : ' . $categorie['libelle'], (new FiliereModel())->find($categorie['filiere_id']), $categorie, site_url('referentiel/categories/' . $id));
    }

    public function update(int $id)
    {
        $categorie = $this->trouver($this->categories, $id);

        if (! $this->peutGerer($categorie['filiere_id'])) {
            return $this->refuser('Vous ne gérez pas cette filière.');
        }

        // Une catégorie ne change pas de filière
        $donnees  = $this->donnees() + ['filiere_id' => (string) $categorie['filiere_id'], 'id' => $id];
        $resultat = $this->enregistrer($this->categories, fn () => $this->categories->update($id, $donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/filieres/' . $categorie['filiere_id'] . '/categories')->with('message', 'Catégorie modifiée.');
    }

    private function donnees(): array
    {
        return [
            'parent_id'         => $this->champ('parent_id'),
            'code'              => strtoupper($this->champ('code')),
            'libelle'           => $this->champ('libelle'),
            'mode_suivi_impose' => $this->champ('mode_suivi_impose'),
            'actif'             => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $filiere, array $categorie, string $action)
    {
        $parents = [];
        foreach ($this->categories->arbreDeLaFiliere($filiere['id']) as $c) {
            if ($c['id'] !== ($categorie['id'] ?? null)) {
                $parents[$c['id']] = str_repeat('— ', $c['profondeur']) . $c['libelle'];
            }
        }

        return view('referentiel/categories/form', [
            'titrePage' => $titre,
            'filiere'   => $filiere,
            'categorie' => $categorie,
            'action'    => $action,
            'parents'   => $parents,
            'modes'     => CategorieModel::MODES_SUIVI,
        ]);
    }
}
