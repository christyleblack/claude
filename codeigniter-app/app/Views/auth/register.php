<?php $this->setVar('titrePage', lang('Auth.register')) ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<div class="carte-auth">
    <h2><?= lang('Auth.register') ?></h2>

    <?= view('auth/_messages') ?>

    <form action="<?= url_to('register') ?>" method="post">
        <?= csrf_field() ?>

        <label for="email"><?= lang('Auth.email') ?></label>
        <input type="email" id="email" name="email" inputmode="email" autocomplete="email" value="<?= esc(old('email')) ?>" required>

        <label for="username"><?= lang('Auth.username') ?></label>
        <input type="text" id="username" name="username" autocomplete="username" value="<?= esc(old('username')) ?>" required>

        <label for="password"><?= lang('Auth.password') ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>

        <label for="password_confirm"><?= lang('Auth.passwordConfirm') ?></label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

        <p><button class="bouton" type="submit"><?= lang('Auth.register') ?></button></p>
    </form>

    <p><?= lang('Auth.haveAccount') ?> <a href="<?= url_to('login') ?>"><?= lang('Auth.login') ?></a></p>
</div>
<?= $this->endSection() ?>
