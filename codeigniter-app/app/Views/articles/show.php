<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<article>
    <h2><?= esc($article['titre']) ?></h2>
    <p class="date">Publié le <?= esc($article['created_at']) ?></p>
    <div class="contenu"><?= esc($article['contenu']) ?></div>
</article>
<p><a href="<?= site_url('articles') ?>">Retour à la liste</a></p>
<?= $this->endSection() ?>
