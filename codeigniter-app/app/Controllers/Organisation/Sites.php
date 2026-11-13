<?php

namespace App\Controllers\Organisation;

use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\SiteModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Sites (lieux physiques) d'une entité et leurs emplacements de stockage (REF-06, H-01).
 */
class Sites extends OrganisationController
{
    private SiteModel $sites;

    public function __construct()
    {
        $this->sites = new SiteModel();
    }

    public function show(int $id)
    {
        $site = $this->trouver($this->sites, $id);

        return view('organisation/sites/show', [
            'titrePage'    => $site['nom'],
            'site'         => $site,
            'entite'       => (new EntiteModel())->find($site['entite_id']),
            'emplacements' => (new EmplacementModel())->arbreDuSite($id),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouveau site', [
            'actif'     => true,
            'entite_id' => $this->request->getGet('entite'),
        ], site_url('organisation/sites'));
    }

    public function create()
    {
        if (! $this->peutGerer((int) $this->champ('entite_id'))) {
            return $this->refuser();
        }

        $resultat = $this->enregistrer($this->sites, fn () => $this->sites->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/sites/' . $resultat)->with('message', 'Site créé.');
    }

    public function edit(int $id)
    {
        $site = $this->trouver($this->sites, $id);

        if (! $this->peutGerer($site['entite_id'])) {
            return redirect()->to('organisation/sites/' . $id)->with('erreur', 'Vous n\'avez pas le droit de modifier ce site.');
        }

        return $this->formulaire('Modifier : ' . $site['nom'], $site, site_url('organisation/sites/' . $id));
    }

    public function update(int $id)
    {
        $site = $this->trouver($this->sites, $id);

        if (! $this->peutGerer($site['entite_id']) || ! $this->peutGerer((int) $this->champ('entite_id'))) {
            return $this->refuser();
        }

        $resultat = $this->enregistrer($this->sites, fn () => $this->sites->update($id, $this->donnees() + ['id' => $id]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/sites/' . $id)->with('message', 'Site modifié.');
    }

    private function donnees(): array
    {
        return [
            'code'      => strtoupper($this->champ('code')),
            'nom'       => $this->champ('nom'),
            'adresse'   => $this->champ('adresse'),
            'entite_id' => $this->champ('entite_id'),
            'actif'     => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $site, string $action)
    {
        return view('organisation/sites/form', [
            'titrePage' => $titre,
            'site'      => $site,
            'action'    => $action,
            'entites'   => (new EntiteModel())->options(),
        ]);
    }
}
