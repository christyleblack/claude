<?php $onglets = ['entites' => 'Entités', 'forces' => 'Forces', 'types' => 'Types d\'entités'] ?>
<nav class="onglets">
    <?php foreach ($onglets as $chemin => $libelle): ?>
        <a class="bouton <?= $chemin === $onglet ? '' : 'secondaire' ?>" href="<?= site_url('organisation/' . $chemin) ?>"><?= esc($libelle) ?></a>
    <?php endforeach ?>
</nav>
