<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?php if ($articles === []): ?>
    <p>Aucun article pour le moment. <a href="<?= site_url('articles/new') ?>">Écrire le premier</a>.</p>
<?php else: ?>
    <?php foreach ($articles as $article): ?>
        <div class="article">
            <h2><a href="<?= site_url('articles/' . $article['id']) ?>"><?= esc($article['titre']) ?></a></h2>
            <span class="date">Publié le <?= esc($article['created_at']) ?></span>
        </div>
    <?php endforeach ?>
<?php endif ?>
<?= $this->endSection() ?>
