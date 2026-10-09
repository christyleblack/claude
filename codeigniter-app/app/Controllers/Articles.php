<?php

namespace App\Controllers;

use App\Models\ArticleModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Articles extends BaseController
{
    public function index()
    {
        return view('articles/index', [
            'titrePage' => 'Articles',
            'articles'  => (new ArticleModel())->avecAuteur()->orderBy('articles.id', 'DESC')->findAll(),
        ]);
    }

    public function show(int $id)
    {
        $article = $this->trouver($id);

        return view('articles/show', [
            'titrePage' => $article['titre'],
            'article'   => $article,
        ]);
    }

    public function new()
    {
        return view('articles/new', ['titrePage' => 'Nouvel article']);
    }

    public function create()
    {
        $model = new ArticleModel();
        $id    = $model->insert([
            'user_id' => auth()->id(),
            'titre'   => $this->request->getPost('titre'),
            'contenu' => $this->request->getPost('contenu'),
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('erreurs', $model->errors());
        }

        return redirect()->to('articles/' . $id)->with('message', 'Article enregistré.');
    }

    public function edit(int $id)
    {
        $article = $this->trouver($id);

        if (! peut_modifier_article($article)) {
            return $this->refuser($id);
        }

        return view('articles/edit', [
            'titrePage' => 'Modifier : ' . $article['titre'],
            'article'   => $article,
        ]);
    }

    public function update(int $id)
    {
        if (! peut_modifier_article($this->trouver($id))) {
            return $this->refuser($id);
        }

        $model = new ArticleModel();
        $ok    = $model->update($id, [
            'titre'   => $this->request->getPost('titre'),
            'contenu' => $this->request->getPost('contenu'),
        ]);

        if (! $ok) {
            return redirect()->back()->withInput()->with('erreurs', $model->errors());
        }

        return redirect()->to('articles/' . $id)->with('message', 'Article modifié.');
    }

    public function delete(int $id)
    {
        if (! peut_modifier_article($this->trouver($id))) {
            return $this->refuser($id);
        }

        (new ArticleModel())->delete($id);

        return redirect()->to('articles')->with('message', 'Article supprimé.');
    }

    private function trouver(int $id): array
    {
        $article = (new ArticleModel())->avecAuteur()->find($id);

        if ($article === null) {
            throw PageNotFoundException::forPageNotFound('Article introuvable.');
        }

        return $article;
    }

    private function refuser(int $id)
    {
        return redirect()->to('articles/' . $id)
            ->with('erreur', 'Vous ne pouvez modifier ou supprimer que vos propres articles.');
    }
}
