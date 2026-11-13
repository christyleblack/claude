<?php $couleurs = ['active' => '#e7f6e7', 'à venir' => '#eef3fb', 'terminée' => '#eee', 'révoquée' => '#fdecea'] ?>
<span class="badge" style="background: <?= $couleurs[$statut] ?? '#eee' ?>"><?= esc($statut) ?></span>
