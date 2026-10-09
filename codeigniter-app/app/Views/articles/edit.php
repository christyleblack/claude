<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Modifier l'article</h2>

<?= view('articles/_form', ['action' => site_url('articles/' . $article['id']), 'article' => $article]) ?>

<p><a href="<?= site_url('articles/' . $article['id']) ?>">Annuler</a></p>
<?= $this->endSection() ?>
