<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?php if (auth()->loggedIn()): ?>
    <p><a class="bouton" href="<?= site_url('articles/new') ?>">Nouvel article</a></p>
<?php endif ?>
<?php if ($articles === []): ?>
    <p>Aucun article pour le moment.
    <?php if (auth()->loggedIn()): ?><a href="<?= site_url('articles/new') ?>">Écrire le premier</a>.<?php endif ?>
</p>
<?php else: ?>
    <?php foreach ($articles as $article): ?>
        <div class="article">
            <h2><a href="<?= site_url('articles/' . $article['id']) ?>"><?= esc($article['titre']) ?></a></h2>
            <span class="date">Publié le <?= esc($article['created_at']) ?> par <?= esc($article['auteur'] ?? 'auteur inconnu') ?></span>
        </div>
    <?php endforeach ?>
<?php endif ?>
<?= $this->endSection() ?>
