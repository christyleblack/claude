<?php

namespace App\Controllers\Demandes;

use App\Controllers\GestionController;
use App\Models\Demandes\CircuitEtapeModel;
use App\Models\Demandes\CircuitModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Paramétrage des circuits de validation (DEM-03, ADM-02), sans développement : un circuit
 * par filière, plus un circuit par défaut ; chaque étape désigne l'entité qui valide.
 */
class Circuits extends GestionController
{
    protected array $contraintes = [
        'circuit_unique_par_filiere' => 'Cette filière a déjà un circuit (ou le circuit par défaut existe déjà).',
        'etape_ordre_unique'         => 'Ce rang est déjà pris dans le circuit.',
        'parametres_du_mode'         => 'Indiquez le niveau pour un échelon logistique, l\'entité pour une entité désignée, et rien d\'autre.',
    ];

    private CircuitModel $circuits;

    public function __construct()
    {
        $this->circuits = new CircuitModel();
    }

    public function index()
    {
        $circuits = $this->circuits->liste();
        foreach ($circuits as &$c) {
            $c['etapes'] = $this->circuits->etapes((int) $c['id']);
        }

        return view('demandes/circuits/index', [
            'titrePage' => 'Circuits de validation',
            'circuits'  => $circuits,
            'filieres'  => (new FiliereModel())->options(),
            'entites'   => (new EntiteModel())->options(),
        ]);
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->circuits, fn () => $this->circuits->insert([
            'libelle'    => $this->champ('libelle'),
            'filiere_id' => $this->champ('filiere_id'),
            'actif'      => true,
        ]));

        return $resultat instanceof RedirectResponse ? $resultat : redirect()->to('demandes/circuits')->with('message', 'Circuit créé : ajoutez ses étapes.');
    }

    public function update(int $id)
    {
        $this->trouver($this->circuits, $id);

        $resultat = $this->enregistrer($this->circuits, fn () => $this->circuits->update($id, [
            'libelle' => $this->champ('libelle'),
            'actif'   => $this->case('actif'),
        ]));

        return $resultat instanceof RedirectResponse ? $resultat : redirect()->to('demandes/circuits')->with('message', 'Circuit modifié.');
    }

    public function ajouterEtape(int $id)
    {
        $this->trouver($this->circuits, $id);
        $etapes = new CircuitEtapeModel();
        $mode   = $this->champ('mode');

        $resultat = $this->enregistrer($etapes, fn () => $etapes->insert([
            'circuit_id'     => $id,
            'ordre'          => $this->champ('ordre'),
            'libelle'        => $this->champ('libelle'),
            'mode'           => $mode,
            'niveau'         => $mode === 'logistique' ? ($this->champ('niveau') ?: null) : null,
            'entite_id'      => $mode === 'entite' ? ($this->champ('entite_id') ?: null) : null,
            'seuil_quantite' => str_replace(',', '.', $this->champ('seuil_quantite')) ?: null,
        ]));

        return $resultat instanceof RedirectResponse ? $resultat : redirect()->to('demandes/circuits')->with('message', 'Étape ajoutée.');
    }

    public function supprimerEtape(int $id, int $etape)
    {
        try {
            (new CircuitEtapeModel())->where('circuit_id', $id)->delete($etape);
        } catch (Throwable) {
            return redirect()->to('demandes/circuits')->with('erreur', 'Suppression refusée.');
        }

        return redirect()->to('demandes/circuits')->with('message', 'Étape supprimée (les demandes déjà soumises gardent leurs étapes).');
    }
}
