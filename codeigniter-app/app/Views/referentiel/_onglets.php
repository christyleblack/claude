<?php $onglets = ['articles' => 'Articles', 'filieres' => 'Filières et catégories'] ?>
<nav class="onglets">
    <?php foreach ($onglets as $chemin => $libelle): ?>
        <a class="bouton <?= $chemin === $onglet ? '' : 'secondaire' ?>" href="<?= site_url('referentiel/' . $chemin) ?>"><?= esc($libelle) ?></a>
    <?php endforeach ?>
</nav>
