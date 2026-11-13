<?php

namespace App\Controllers\Referentiel;

use App\Models\Referentiel\ArticleForceModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Articles génériques nationaux : consultation par tous, gestion par les gestionnaires
 * de la filière de l'article (referentiel.gerer, éventuellement restreint à une filière).
 */
class Articles extends ReferentielController
{
    private const PAR_PAGE = 50;

    private ArticleModel $articles;

    public function __construct()
    {
        $this->articles = new ArticleModel();
    }

    public function index()
    {
        $filtres = [
            'q'           => trim((string) $this->request->getGet('q')),
            'filiere'     => (string) $this->request->getGet('filiere'),
            'categorie'   => (string) $this->request->getGet('categorie'),
            'mode'        => (string) $this->request->getGet('mode'),
            'sensibilite' => (string) $this->request->getGet('sensibilite'),
            'inactifs'    => $this->request->getGet('inactifs') === '1',
        ];
        $page              = max(1, (int) $this->request->getGet('page'));
        [$lignes, $suivante] = $this->articles->rechercher($filtres, $page, self::PAR_PAGE);

        return view('referentiel/articles/index', [
            'titrePage'  => 'Articles',
            'articles'   => $lignes,
            'suivante'   => $suivante,
            'page'       => $page,
            'filtres'    => $filtres,
            'filieres'   => (new FiliereModel())->options(),
            'categories' => (new CategorieModel())->optionsParFiliere(),
        ]);
    }

    public function show(int $id)
    {
        $article  = $this->trouver($this->articles, $id);
        $filiere  = (new FiliereModel())->find($article['filiere_id']);
        $forces   = db_connect()->table('force')->orderBy('nom')->get()->getResultArray();
        $fiches   = (new ArticleForceModel())->deLArticle($id);
        $avecFiche = array_column($fiches, 'force_id');

        return view('referentiel/articles/show', [
            'titrePage'      => $article['designation'],
            'article'        => $article,
            'filiere'        => $filiere,
            'categorie'      => (new CategorieModel())->find($article['categorie_id']),
            'unite'          => $this->articles->unites()[$article['unite_gestion']] ?? $article['unite_gestion'],
            'fiches'         => $fiches,
            'peutGerer'      => $this->peutGerer($article['filiere_id']),
            // Forces sans fiche pour lesquelles l'utilisateur peut en créer une
            'forcesAjout'    => array_filter($forces, static fn ($f) => ! in_array((int) $f['id'], array_map('intval', $avecFiche), true)
                && service('droits')->peutForce('referentiel.fiche_force', (int) $f['id'])),
        ]);
    }

    public function new()
    {
        return $this->formulaire('Nouvel article', ['actif' => true, 'sensibilite' => 'courant', 'mode_suivi' => 'quantite', 'unite_gestion' => 'U'], site_url('referentiel/articles'));
    }

    public function create()
    {
        $donnees = $this->donnees();

        if (! $this->peutGerer((int) $donnees['filiere_id'])) {
            return $this->refuser('Vous ne gérez pas la filière de cette catégorie.');
        }

        $resultat = $this->enregistrer($this->articles, fn () => $this->articles->insert($donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/articles/' . $resultat)->with('message', 'Article créé.');
    }

    public function edit(int $id)
    {
        $article = $this->trouver($this->articles, $id);

        if (! $this->peutGerer($article['filiere_id'])) {
            return redirect()->to('referentiel/articles/' . $id)->with('erreur', 'Vous ne gérez pas la filière de cet article.');
        }

        return $this->formulaire('Modifier : ' . $article['designation'], $article, site_url('referentiel/articles/' . $id));
    }

    public function update(int $id)
    {
        $article = $this->trouver($this->articles, $id);
        $donnees = $this->donnees() + ['id' => $id];

        if (! $this->peutGerer($article['filiere_id']) || ! $this->peutGerer((int) $donnees['filiere_id'])) {
            return $this->refuser('Vous ne gérez pas la filière de cet article ou de la nouvelle catégorie.');
        }

        $resultat = $this->enregistrer($this->articles, fn () => $this->articles->update($id, $donnees));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('referentiel/articles/' . $id)->with('message', 'Article modifié.');
    }

    /**
     * La filière découle de la catégorie choisie (RG-05).
     */
    private function donnees(): array
    {
        $categorie = (new CategorieModel())->find((int) $this->champ('categorie_id'));

        return [
            'codification'  => strtoupper($this->champ('codification')),
            'designation'   => $this->champ('designation'),
            'unite_gestion' => $this->champ('unite_gestion'),
            'categorie_id'  => $this->champ('categorie_id'),
            'filiere_id'    => $categorie === null ? '' : (string) $categorie['filiere_id'],
            'mode_suivi'    => $this->champ('mode_suivi'),
            'sensibilite'   => $this->champ('sensibilite'),
            'actif'         => $this->case('actif'),
        ];
    }

    private function formulaire(string $titre, array $article, string $action)
    {
        return view('referentiel/articles/form', [
            'titrePage'    => $titre,
            'article'      => $article,
            'action'       => $action,
            'categories'   => (new CategorieModel())->optionsParFiliere(),
            'unites'       => $this->articles->unites(),
            'modes'        => CategorieModel::MODES_SUIVI,
            'sensibilites' => ArticleModel::SENSIBILITES,
        ]);
    }
}
