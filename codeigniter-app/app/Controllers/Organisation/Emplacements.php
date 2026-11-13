<?php

namespace App\Controllers\Organisation;

use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\SiteModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Emplacements de stockage d'un site, éventuellement imbriqués.
 */
class Emplacements extends OrganisationController
{
    private EmplacementModel $emplacements;

    public function __construct()
    {
        $this->emplacements = new EmplacementModel();
    }

    public function new()
    {
        $site = (new SiteModel())->find((int) $this->request->getGet('site'));

        if ($site === null) {
            throw PageNotFoundException::forPageNotFound('Site introuvable.');
        }

        return $this->formulaire('Nouvel emplacement', $site, [
            'actif'     => true,
            'site_id'   => $site['id'],
            'parent_id' => $this->request->getGet('parent'),
        ], site_url('organisation/emplacements'));
    }

    public function create()
    {
        $resultat = $this->enregistrer($this->emplacements, fn () => $this->emplacements->insert($this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/sites/' . $this->champ('site_id'))->with('message', 'Emplacement créé.');
    }

    public function edit(int $id)
    {
        $emplacement = $this->trouver($this->emplacements, $id);
        $site        = (new SiteModel())->find($emplacement['site_id']);

        return $this->formulaire('Modifier : ' . $emplacement['libelle'], $site, $emplacement, site_url('organisation/emplacements/' . $id));
    }

    public function update(int $id)
    {
        $emplacement = $this->trouver($this->emplacements, $id);
        // Le site d'un emplacement ne change pas
        $donnees = ['site_id' => $emplacement['site_id']] + $this->donnees() + ['id' => $id];

        $resultat = $this->enregistrer($this->emplacements, fn () => $this->emplacements->update($id, $donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('organisation/sites/' . $emplacement['site_id'])->with('message', 'Emplacement modifié.');
    }

    private function donnees(): array
    {
        return [
            'site_id'   => $this->champ('site_id'),
            'parent_id' => $this->champ('parent_id'),
            'code'      => strtoupper($this->champ('code')),
            'libelle'   => $this->champ('libelle'),
            'actif'     => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $site, array $emplacement, string $action)
    {
        $parents = [];
        foreach ($this->emplacements->arbreDuSite((int) $site['id']) as $e) {
            if ((int) $e['id'] !== (int) ($emplacement['id'] ?? 0)) {
                $parents[(int) $e['id']] = str_repeat('— ', $e['profondeur']) . $e['code'] . ' – ' . $e['libelle'];
            }
        }

        return view('organisation/emplacements/form', [
            'titrePage'   => $titre,
            'site'        => $site,
            'emplacement' => $emplacement,
            'action'      => $action,
            'parents'     => $parents,
        ]);
    }
}
