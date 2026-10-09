<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($titrePage ?? 'Mon application') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 760px; margin: 0 auto; padding: 0 16px 40px; color: #222; background: #fafafa; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; margin-bottom: 24px; }
        header a { color: #222; text-decoration: none; }
        a { color: #dd4814; }
        .bouton { display: inline-block; background: #dd4814; color: #fff; padding: 8px 14px; border-radius: 4px; text-decoration: none; border: 0; font-size: 1rem; cursor: pointer; }
        .article { background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; }
        .article h2 { margin: 0 0 4px; font-size: 1.2rem; }
        .date { color: #777; font-size: .85rem; }
        .message { background: #e7f6e7; border: 1px solid #b6e0b6; padding: 10px; border-radius: 4px; }
        .erreurs { background: #fdecea; border: 1px solid #f5c2bd; padding: 10px; border-radius: 4px; }
        ul.erreurs { padding-left: 30px; }
        label { display: block; margin: 16px 0 4px; font-weight: 600; }
        input[type=text], input[type=email], input[type=password], textarea { width: 100%; box-sizing: border-box; padding: 8px; font: inherit; border: 1px solid #ccc; border-radius: 4px; }
        textarea { min-height: 180px; }
        .compte { display: flex; gap: 12px; align-items: center; }
        .compte a:not(.bouton) { color: #dd4814; }
        .actions { display: flex; gap: 8px; align-items: center; margin-top: 24px; }
        .actions form { margin: 0; }
        .bouton.secondaire { background: #555; }
        .bouton.danger { background: #b3261e; }
        .carte-auth { max-width: 420px; margin: 0 auto; background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 8px 24px 16px; }
        label.case { display: flex; gap: 8px; align-items: center; font-weight: normal; }
        .contenu { white-space: pre-line; line-height: 1.6; }
    </style>
</head>
<body>
    <header>
        <h1><a href="<?= site_url('articles') ?>">Mes articles</a></h1>
        <nav class="compte">
            <?php if (auth()->loggedIn()): ?>
                <span><?= esc(auth()->user()->username) ?></span>
                <a href="<?= site_url('logout') ?>">Déconnexion</a>
                <a class="bouton" href="<?= site_url('articles/new') ?>">Nouvel article</a>
            <?php else: ?>
                <a href="<?= site_url('login') ?>">Connexion</a>
                <a href="<?= site_url('register') ?>">Inscription</a>
            <?php endif ?>
        </nav>
    </header>

    <?php if (session()->getFlashdata('message')): ?>
        <p class="message"><?= esc(session()->getFlashdata('message')) ?></p>
    <?php endif ?>
    <?php if (session()->getFlashdata('erreur')): ?>
        <p class="erreurs"><?= esc(session()->getFlashdata('erreur')) ?></p>
    <?php endif ?>

    <?= $this->renderSection('contenu') ?>
</body>
</html>
