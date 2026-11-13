<?php

namespace App\Controllers\Organisation;

use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Entités de l'organisation et leurs arbres hiérarchique et logistique (REF-06, RG-01).
 */
class Entites extends OrganisationController
{
    private EntiteModel $entites;

    public function __construct()
    {
        $this->entites = new EntiteModel();
    }

    public function index()
    {
        $axe = $this->request->getGet('axe');
        $axe = in_array($axe, EntiteModel::AXES, true) ? $axe : 'hierarchique';

        return view('organisation/entites/index', [
            'titrePage' => 'Entités',
            'axe'       => $axe,
            'entites'   => $this->entites->arbre($axe),
        ]);
    }

    public function show(int $id)
    {
        $entite = $this->trouver($this->entites, $id);
        $parent = fn (?int $idParent) => $idParent === null ? null : $this->entites->find($idParent);

        return view('organisation/entites/show', [
            'titrePage'   => $entite['nom'],
            'entite'      => $entite,
            'type'        => (new TypeEntiteModel())->find($entite['type_entite_id']),
            'force'       => $entite['force_id'] === null ? null : (new ForceModel())->find($entite['force_id']),
            'forceRacine' => (new ForceModel())->where('entite_racine_id', $id)->first(),
            'parentH'     => $parent($entite['parent_hierarchique_id']),
            'parentL'     => $parent($entite['parent_logistique_id']),
            'ancetresH'   => $this->entites->ancetres($id, 'hierarchique'),
            'ancetresL'   => $this->entites->ancetres($id, 'logistique'),
            'enfantsH'    => $this->entites->enfants($id, 'hierarchique'),
            'enfantsL'    => $this->entites->enfants($id, 'logistique'),
            'sites'       => (new SiteModel())->where('entite_id', $id)->orderBy('nom')->findAll(),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouvelle entité', [
            'actif'                  => true,
            'parent_hierarchique_id' => $this->request->getGet('parent_hierarchique'),
            'parent_logistique_id'   => $this->request->getGet('parent_logistique'),
        ], site_url('organisation/entites'));
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->entites, fn () => $this->entites->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/entites/' . $resultat)->with('message', 'Entité créée.');
    }

    public function edit(int $id)
    {
        $entite = $this->trouver($this->entites, $id);

        return $this->formulaire('Modifier : ' . $entite['nom'], $entite, site_url('organisation/entites/' . $id));
    }

    public function update(int $id)
    {
        $this->trouver($this->entites, $id);
        $donnees = $this->donnees() + ['id' => $id];

        // Message clair avant que la base ne refuse le cycle
        foreach (EntiteModel::AXES as $axe) {
            $parent = $donnees['parent_' . $axe . '_id'];
            if ($parent !== '' && in_array((int) $parent, $this->entites->sousArbre($id, $axe), true)) {
                $libelle = $axe === 'hierarchique' ? 'hiérarchique' : 'logistique';

                return redirect()->back()->withInput()->with('erreurs', [
                    "Le parent {$libelle} choisi est l'entité elle-même ou une de ses subordonnées : cela créerait un cycle.",
                ]);
            }
        }

        $resultat = $this->enregistrer($this->entites, fn () => $this->entites->update($id, $donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/entites/' . $id)->with('message', 'Entité modifiée.');
    }

    private function donnees(): array
    {
        return [
            'code'                       => strtoupper($this->champ('code')),
            'nom'                        => $this->champ('nom'),
            'type_entite_id'             => $this->champ('type_entite_id'),
            'parent_hierarchique_id'     => $this->champ('parent_hierarchique_id'),
            'parent_logistique_id'       => $this->champ('parent_logistique_id'),
            'signature_nominative'       => $this->case('signature_nominative'),
            'inventaire_contradictoire'  => $this->case('inventaire_contradictoire'),
            'restitution_fin_engagement' => $this->case('restitution_fin_engagement'),
            'actif'                      => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $entite, string $action)
    {
        $options = $this->entites->options();

        // En modification, l'entité ne peut pas être son propre parent
        if (isset($entite['id'])) {
            unset($options[(int) $entite['id']]);
        }

        return view('organisation/entites/form', [
            'titrePage' => $titre,
            'entite'    => $entite,
            'action'    => $action,
            'types'     => (new TypeEntiteModel())->options(),
            'parents'   => $options,
        ]);
    }
}
