<?php $couleurs = ['planifie' => '#eef3fb', 'en_cours' => '#fff4d6', 'compte' => '#fde9d9', 'valide' => '#e7f6e7', 'annule' => '#eee'] ?>
<span class="badge" style="background: <?= $couleurs[$statut] ?? '#eee' ?>"><?= esc(\App\Models\Inventaires\InventaireModel::STATUTS[$statut] ?? $statut) ?></span>
