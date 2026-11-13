<?php

namespace App\Controllers\Referentiel;

use App\Models\Organisation\EntiteModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Filières et service gestionnaire (RG-05) : gérées avec referentiel.gerer non restreint.
 */
class Filieres extends ReferentielController
{
    private FiliereModel $filieres;

    public function __construct()
    {
        $this->filieres = new FiliereModel();
    }

    public function index()
    {
        $filieres = db_connect()->table('filiere f')
            ->select('f.*, e.nom AS service, (SELECT count(*) FROM article a WHERE a.filiere_id = f.id AND a.actif) AS nb_articles')
            ->join('entite e', 'e.id = f.service_gestionnaire_id', 'left')
            ->orderBy('f.libelle')->get()->getResultArray();

        return view('referentiel/filieres/index', [
            'titrePage' => 'Filières',
            'filieres'  => $filieres,
            'peutGerer' => $this->peutGerer(),
        ]);
    }

    public function new()
    {
        if (! $this->peutGerer()) {
            return redirect()->to('referentiel/filieres')->with('erreur', 'Les filières se gèrent avec une affectation non limitée à une filière.');
        }

        return $this->formulaire('Nouvelle filière', ['actif' => true], site_url('referentiel/filieres'));
    }

    public function create()
    {
        if (! $this->peutGerer()) {
            return $this->refuser('Les filières se gèrent avec une affectation non limitée à une filière.');
        }

        $resultat = $this->enregistrer($this->filieres, fn () => $this->filieres->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/filieres')->with('message', 'Filière créée.');
    }

    public function edit(int $id)
    {
        $filiere = $this->trouver($this->filieres, $id);

        if (! $this->peutGerer()) {
            return redirect()->to('referentiel/filieres')->with('erreur', 'Les filières se gèrent avec une affectation non limitée à une filière.');
        }

        return $this->formulaire('Modifier : ' . $filiere['libelle'], $filiere, site_url('referentiel/filieres/' . $id));
    }

    public function update(int $id)
    {
        $this->trouver($this->filieres, $id);

        if (! $this->peutGerer()) {
            return $this->refuser('Les filières se gèrent avec une affectation non limitée à une filière.');
        }

        $resultat = $this->enregistrer($this->filieres, fn () => $this->filieres->update($id, $this->donnees() + ['id' => $id]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/filieres')->with('message', 'Filière modifiée.');
    }

    private function donnees(): array
    {
        return [
            'code'                    => strtoupper($this->champ('code')),
            'libelle'                 => $this->champ('libelle'),
            'service_gestionnaire_id' => $this->champ('service_gestionnaire_id'),
            'actif'                   => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $filiere, string $action)
    {
        return view('referentiel/filieres/form', [
            'titrePage' => $titre,
            'filiere'   => $filiere,
            'action'    => $action,
            'services'  => (new EntiteModel())->options(),
        ]);
    }
}
