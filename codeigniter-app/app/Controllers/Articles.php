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
            'articles'  => (new ArticleModel())->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function show(int $id)
    {
        $article = (new ArticleModel())->find($id);

        if ($article === null) {
            throw PageNotFoundException::forPageNotFound('Article introuvable.');
        }

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
            'titre'   => $this->request->getPost('titre'),
            'contenu' => $this->request->getPost('contenu'),
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('erreurs', $model->errors());
        }

        return redirect()->to('articles/' . $id)->with('message', 'Article enregistré.');
    }
}
