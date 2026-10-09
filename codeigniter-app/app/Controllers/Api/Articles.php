<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ArticleModel;

class Articles extends BaseController
{
    public function index()
    {
        return $this->response->setJSON((new ArticleModel())->avecAuteur()->orderBy('articles.id', 'DESC')->findAll());
    }

    public function create()
    {
        $model = new ArticleModel();
        $id    = $model->insert([
            'user_id' => auth('tokens')->id(),
            'titre'   => $this->request->getPost('titre'),
            'contenu' => $this->request->getPost('contenu'),
        ]);

        if ($id === false) {
            return $this->response->setStatusCode(400)->setJSON(['erreurs' => $model->errors()]);
        }

        return $this->response->setStatusCode(201)->setJSON($model->avecAuteur()->find($id));
    }
}
