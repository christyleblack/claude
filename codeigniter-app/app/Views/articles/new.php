<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Nouvel article</h2>

<?= view('articles/_form', ['action' => site_url('articles')]) ?>
<?= $this->endSection() ?>
