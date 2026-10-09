<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<article>
    <h2><?= esc($article['titre']) ?></h2>
    <p class="date">
        Publié le <?= esc($article['created_at']) ?>
        <?php if ($article['updated_at'] !== $article['created_at']): ?>
            · modifié le <?= esc($article['updated_at']) ?>
        <?php endif ?>
    </p>
    <div class="contenu"><?= esc($article['contenu']) ?></div>
</article>

<div class="actions">
    <a class="bouton secondaire" href="<?= site_url('articles/' . $article['id'] . '/edit') ?>">Modifier</a>
    <form action="<?= site_url('articles/' . $article['id'] . '/delete') ?>" method="post"
          onsubmit="return confirm('Supprimer définitivement cet article ?');">
        <?= csrf_field() ?>
        <button class="bouton danger" type="submit">Supprimer</button>
    </form>
</div>

<p><a href="<?= site_url('articles') ?>">Retour à la liste</a></p>
<?= $this->endSection() ?>
