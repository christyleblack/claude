<?php $this->setVar('titrePage', 'Code de confirmation') ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<div class="carte-auth">
    <h2>Code de confirmation</h2>


    <?php if ($envoiSimule ?? false): ?>
        <p class="message">Poste de développement sans serveur de courriel : le code a été écrit dans writable/logs.</p>
    <?php endif ?>

    <p>Saisissez le code à six chiffres reçu par courriel.</p>

    <form action="<?= url_to('auth-action-verify') ?>" method="post">
        <?= csrf_field() ?>

        <label for="token">Code</label>
        <input type="text" id="token" name="token" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>

        <p><button class="bouton" type="submit">Confirmer</button></p>
    </form>
</div>
<?= $this->endSection() ?>
