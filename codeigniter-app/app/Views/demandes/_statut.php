<?php $couleurs = ['brouillon' => '#eee', 'soumise' => '#eef3fb', 'en_validation' => '#fff4d6', 'validee' => '#e7f6e7', 'refusee' => '#fdecea', 'en_service' => '#e3f1f7', 'servie' => '#d9f2d9', 'cloturee' => '#eee', 'annulee' => '#eee'] ?>
<span class="badge" style="background: <?= $couleurs[$statut] ?? '#eee' ?>"><?= esc(\App\Models\Demandes\DemandeModel::STATUTS[$statut] ?? $statut) ?></span>
