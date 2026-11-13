<?php

namespace App\Controllers\Organisation;

use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Forces et paramètres d'autonomie de leur direction du matériel (section 2.5).
 */
class Forces extends OrganisationController
{
    private ForceModel $forces;

    public function __construct()
    {
        $this->forces = new ForceModel();
    }

    public function index()
    {
        return view('organisation/forces/index', [
            'titrePage' => 'Forces',
            'forces'    => $this->forces->avecRacine()->orderBy('force.nom')->findAll(),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouvelle force', [], site_url('organisation/forces'));
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->forces, fn () => $this->forces->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/forces')->with('message', 'Force créée : ses entités subordonnées lui sont rattachées.');
    }

    public function edit(int $id)
    {
        $force = $this->trouver($this->forces, $id);

        return $this->formulaire('Modifier : ' . $force['nom'], $force, site_url('organisation/forces/' . $id));
    }

    public function update(int $id)
    {
        $this->trouver($this->forces, $id);

        $resultat = $this->enregistrer($this->forces, fn () => $this->forces->update($id, $this->donnees() + ['id' => $id]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/forces')->with('message', 'Force modifiée.');
    }

    private function donnees(): array
    {
        return [
            'code'                     => strtoupper($this->champ('code')),
            'nom'                      => $this->champ('nom'),
            'entite_racine_id'         => $this->champ('entite_racine_id'),
            'validation_finale_locale' => $this->case('validation_finale_locale'),
            'achats_propres'           => $this->case('achats_propres'),
            'seuil_quantite_dcma'      => str_replace(',', '.', $this->champ('seuil_quantite_dcma')),
            'seuil_montant_dcma'       => str_replace(',', '.', $this->champ('seuil_montant_dcma')),
        ];
    }

    private function formulaire(string $titre, array $force, string $action)
    {
        return view('organisation/forces/form', [
            'titrePage' => $titre,
            'force'     => $force,
            'action'    => $action,
            'entites'   => (new EntiteModel())->options(),
        ]);
    }
}
