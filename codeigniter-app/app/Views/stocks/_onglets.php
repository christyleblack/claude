<?php $onglets = ['stocks' => 'Stock', 'stocks/mouvements' => 'Mouvements'] ?>
<nav class="onglets">
    <?php foreach ($onglets as $chemin => $libelle): ?>
        <a class="bouton <?= $chemin === $onglet ? '' : 'secondaire' ?>" href="<?= site_url($chemin) ?>"><?= esc($libelle) ?></a>
    <?php endforeach ?>
    <?php if (service('droits')->peut('stocks.mouvementer')): ?>
        <a class="bouton" href="<?= site_url('stocks/mouvements/new') ?>">Saisir un mouvement</a>
    <?php endif ?>
</nav>
