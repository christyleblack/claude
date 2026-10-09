<?php $this->setVar('titrePage', lang('Auth.login')) ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<div class="carte-auth">
    <h2><?= lang('Auth.login') ?></h2>

    <?= view('auth/_messages') ?>

    <form action="<?= url_to('login') ?>" method="post">
        <?= csrf_field() ?>

        <label for="email"><?= lang('Auth.email') ?></label>
        <input type="email" id="email" name="email" inputmode="email" autocomplete="email" value="<?= esc(old('email')) ?>" required>

        <label for="password"><?= lang('Auth.password') ?></label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <label class="case">
                <input type="checkbox" name="remember" <?= old('remember') ? 'checked' : '' ?>>
                <?= lang('Auth.rememberMe') ?>
            </label>
        <?php endif ?>

        <p><button class="bouton" type="submit"><?= lang('Auth.login') ?></button></p>
    </form>

    <?php if (setting('Auth.allowMagicLinkLogins')): ?>
        <p><?= lang('Auth.forgotPassword') ?> <a href="<?= url_to('magic-link') ?>"><?= lang('Auth.useMagicLink') ?></a></p>
    <?php endif ?>

    <?php if (setting('Auth.allowRegistration')): ?>
        <p><?= lang('Auth.needAccount') ?> <a href="<?= url_to('register') ?>"><?= lang('Auth.register') ?></a></p>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
