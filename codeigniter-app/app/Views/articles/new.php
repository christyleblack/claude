<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Nouvel article</h2>

<?php if ($erreurs = session()->getFlashdata('erreurs')): ?>
    <ul class="erreurs">
        <?php foreach ($erreurs as $erreur): ?>
            <li><?= esc($erreur) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<form action="<?= site_url('articles') ?>" method="post">
    <?= csrf_field() ?>

    <label for="titre">Titre</label>
    <input type="text" id="titre" name="titre" value="<?= esc(old('titre')) ?>" required>

    <label for="contenu">Contenu</label>
    <textarea id="contenu" name="contenu"><?= esc(old('contenu')) ?></textarea>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<?= $this->endSection() ?>
