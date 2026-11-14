<?php $this->setVar('titrePage', 'Confirmer la connexion') ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<div class="carte-auth">
    <h2>Confirmer la connexion</h2>


    <p>Votre profil donne accès à des fonctions sensibles : la connexion se confirme par un code à six chiffres envoyé à votre adresse de courriel.</p>

    <form action="<?= url_to('auth-action-handle') ?>" method="post">
        <?= csrf_field() ?>

        <label for="email">Saisissez votre adresse de courriel pour recevoir le code</label>
        <input type="email" id="email" name="email" inputmode="email" autocomplete="email" required>

        <p><button class="bouton" type="submit">Recevoir le code</button></p>
    </form>
</div>
<?= $this->endSection() ?>
