<?php

namespace App\Controllers\Referentiel;

use App\Models\Referentiel\ArticleForceModel;
use App\Models\Referentiel\ArticleModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Fiches propres à une force (REF-02, RG-09) : gérées par la force elle-même
 * (permission referentiel.fiche_force sur son entité racine).
 */
class FichesForce extends ReferentielController
{
    private ArticleForceModel $fiches;

    public function __construct()
    {
        $this->fiches = new ArticleForceModel();
    }

    public function new()
    {
        $article = (new ArticleModel())->find((int) $this->request->getGet('article'));
        $force   = db_connect()->table('force')->where('id', (int) $this->request->getGet('force'))->get()->getRowArray();

        if ($article === null || $force === null) {
            throw PageNotFoundException::forPageNotFound('Article ou force introuvable.');
        }
        if (! $this->peutFicheForce((int) $force['id'])) {
            return redirect()->to('referentiel/articles/' . $article['id'])->with('erreur', 'Vous ne gérez pas les fiches de cette force.');
        }

        return $this->formulaire('Fiche ' . $force['nom'], $article, $force, ['actif' => true], site_url('referentiel/fiches'));
    }

    public function create()
    {
        $forceId = (int) $this->champ('force_id');

        if (! $this->peutFicheForce($forceId)) {
            return $this->refuser('Vous ne gérez pas les fiches de cette force.');
        }

        $donnees = $this->donnees() + ['article_id' => $this->champ('article_id'), 'force_id' => (string) $forceId];

        if ($this->fiches->where(['article_id' => (int) $donnees['article_id'], 'force_id' => $forceId])->first() !== null) {
            return $this->refuser('Cette force a déjà une fiche pour cet article (RG-09) : modifiez-la.');
        }

        $resultat = $this->enregistrer($this->fiches, fn () => $this->fiches->insert($donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/articles/' . $donnees['article_id'])->with('message', 'Fiche de la force enregistrée.');
    }

    public function edit(int $id)
    {
        $fiche = $this->trouver($this->fiches, $id);

        if (! $this->peutFicheForce($fiche['force_id'])) {
            return redirect()->to('referentiel/articles/' . $fiche['article_id'])->with('erreur', 'Vous ne gérez pas les fiches de cette force.');
        }

        $force = db_connect()->table('force')->where('id', $fiche['force_id'])->get()->getRowArray();

        return $this->formulaire('Fiche ' . $force['nom'], (new ArticleModel())->find($fiche['article_id']), $force, $fiche, site_url('referentiel/fiches/' . $id));
    }

    public function update(int $id)
    {
        $fiche = $this->trouver($this->fiches, $id);

        if (! $this->peutFicheForce($fiche['force_id'])) {
            return $this->refuser('Vous ne gérez pas les fiches de cette force.');
        }

        $resultat = $this->enregistrer($this->fiches, fn () => $this->fiches->update($id, $this->donnees()));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/articles/' . $fiche['article_id'])->with('message', 'Fiche de la force modifiée.');
    }

    private function peutFicheForce(int $forceId): bool
    {
        return service('droits')->peutForce('referentiel.fiche_force', $forceId);
    }

    private function donnees(): array
    {
        return [
            'code_local'         => $this->champ('code_local'),
            'designation_locale' => $this->champ('designation_locale'),
            'attributs'          => json_encode(ArticleForceModel::attributsDepuisTexte($this->champ('attributs')), JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT),
            'actif'              => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $article, array $force, array $fiche, string $action)
    {
        return view('referentiel/fiches/form', [
            'titrePage' => $titre,
            'article'   => $article,
            'force'     => $force,
            'fiche'     => $fiche,
            'action'    => $action,
        ]);
    }
}
