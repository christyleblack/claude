<?php

namespace App\Controllers;

use App\Models\ArticleModel;

class Articles extends BaseController
{
    public function index()
    {
        $articles = (new ArticleModel())->orderBy('id', 'DESC')->findAll();

        return $this->response->setJSON($articles);
    }

    public function create()
    {
        $model = new ArticleModel();
        $id    = $model->insert([
            'titre'   => $this->request->getPost('titre'),
            'contenu' => $this->request->getPost('contenu'),
        ]);

        if ($id === false) {
            return $this->response->setStatusCode(400)->setJSON(['erreurs' => $model->errors()]);
        }

        return $this->response->setStatusCode(201)->setJSON($model->find($id));
    }
}
