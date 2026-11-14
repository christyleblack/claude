<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($titrePage ?? 'Gestion du matériel') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 1100px; margin: 0 auto; padding: 0 16px 40px; color: #222; background: #fafafa; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; margin-bottom: 24px; }
        header h1 a { color: #222; text-decoration: none; }
        nav.principale { display: flex; gap: 16px; margin: -12px 0 20px; flex-wrap: wrap; }
        nav.principale a { text-decoration: none; }
        nav.principale a.actif { font-weight: 700; text-decoration: underline; }
        a { color: #dd4814; }
        .bouton { display: inline-block; background: #dd4814; color: #fff; padding: 8px 14px; border-radius: 4px; text-decoration: none; border: 0; font-size: 1rem; cursor: pointer; }
        .article { background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; }
        .article h2 { margin: 0 0 4px; font-size: 1.2rem; }
        .date { color: #777; font-size: .85rem; }
        .message { background: #e7f6e7; border: 1px solid #b6e0b6; padding: 10px; border-radius: 4px; }
        .erreurs { background: #fdecea; border: 1px solid #f5c2bd; padding: 10px; border-radius: 4px; }
        ul.erreurs { padding-left: 30px; }
        label { display: block; margin: 16px 0 4px; font-weight: 600; }
        input[type=text], input[type=email], input[type=password], input[type=number], input[type=date], select, textarea { width: 100%; box-sizing: border-box; padding: 8px; font: inherit; border: 1px solid #ccc; border-radius: 4px; }
        textarea { min-height: 180px; }
        .compte { display: flex; gap: 12px; align-items: center; }
        .compte a:not(.bouton) { color: #dd4814; }
        .actions { display: flex; gap: 8px; align-items: center; margin-top: 24px; }
        .actions form { margin: 0; }
        .bouton.secondaire { background: #555; }
        .bouton.danger { background: #b3261e; }
        .carte-auth { max-width: 420px; margin: 0 auto; background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 8px 24px 16px; }
        label.case { display: flex; gap: 8px; align-items: center; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        th { background: #f2f2f2; }
        .inactif { color: #999; }
        .badge { display: inline-block; font-size: .8rem; padding: 1px 6px; border-radius: 10px; background: #eee; color: #444; }
        .fil { color: #666; font-size: .9rem; }
        .grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 0 24px; }
        .onglets { display: flex; gap: 8px; margin: 8px 0 16px; }
        .contenu { white-space: pre-line; line-height: 1.6; }
    </style>
</head>
<body>
    <header>
        <h1><a href="<?= site_url('/') ?>">Gestion du matériel</a></h1>
        <nav class="compte">
            <?php if (auth()->loggedIn()): ?>
                <span><?= esc(auth()->user()->username) ?></span>
                <a href="<?= site_url('logout') ?>">Déconnexion</a>
            <?php else: ?>
                <a href="<?= site_url('login') ?>">Connexion</a>
                <a href="<?= site_url('register') ?>">Inscription</a>
            <?php endif ?>
        </nav>
    </header>

    <?php if (auth()->loggedIn()): ?>
        <?php
        $segments = service('request')->getUri()->getSegments();
        $segment  = $segments[0] ?? '';
        $page     = $segment . '/' . ($segments[1] ?? '');
        ?>
        <nav class="principale">
            <a href="<?= site_url('organisation/entites') ?>" class="<?= $segment === 'organisation' ? 'actif' : '' ?>">Organisation</a>
            <a href="<?= site_url('referentiel/articles') ?>" class="<?= $segment === 'referentiel' ? 'actif' : '' ?>">Référentiel</a>
            <a href="<?= site_url('stocks') ?>" class="<?= $segment === 'stocks' ? 'actif' : '' ?>">Stocks</a>
            <a href="<?= site_url('articles') ?>" class="<?= $segment === 'articles' ? 'actif' : '' ?>">Articles (exemple)</a>
            <a href="<?= site_url('administration/mes-droits') ?>" class="<?= in_array($page, ['administration/mes-droits', 'administration/delegations'], true) ? 'actif' : '' ?>">Mes droits</a>
            <?php if (service('droits')->peut('administration.utilisateurs')): ?><a href="<?= site_url('administration/utilisateurs') ?>" class="<?= $page === 'administration/utilisateurs' ? 'actif' : '' ?>">Utilisateurs</a><?php endif ?>
            <?php if (service('droits')->peut('administration.roles')): ?><a href="<?= site_url('administration/roles') ?>" class="<?= $page === 'administration/roles' ? 'actif' : '' ?>">Rôles</a><?php endif ?>
            <?php if (service('droits')->peut('audit.consulter')): ?><a href="<?= site_url('administration/journal') ?>" class="<?= $page === 'administration/journal' ? 'actif' : '' ?>">Journal d'audit</a><?php endif ?>
        </nav>
    <?php endif ?>

    <?php if (session()->getFlashdata('message')): ?>
        <p class="message"><?= esc(session()->getFlashdata('message')) ?></p>
    <?php endif ?>
    <?php if (session()->getFlashdata('erreur')): ?>
        <p class="erreurs"><?= esc(session()->getFlashdata('erreur')) ?></p>
    <?php endif ?>
    <?php // Erreurs de Shield (permission refusée…) ; les pages de connexion et d'inscription les affichent elles-mêmes ?>
    <?php if (is_string(session()->getFlashdata('error')) && ! preg_match('#(^|/)(login|register)$#', service('request')->getUri()->getPath())): ?>
        <p class="erreurs"><?= esc(session()->getFlashdata('error')) ?></p>
    <?php endif ?>

    <?= $this->renderSection('contenu') ?>
</body>
</html>
