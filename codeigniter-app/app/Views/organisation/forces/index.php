<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'forces']) ?>

<h2>Forces</h2>
<?php $peutGerer = service('droits')->peut('organisation.gerer', null, true) ?>
<?php if ($peutGerer): ?>
    <p><a class="bouton" href="<?= site_url('organisation/forces/new') ?>">Nouvelle force</a></p>
<?php endif ?>

<p class="fil">Une force est rattachée à son entité racine : toutes les entités qui en dépendent hiérarchiquement lui appartiennent.</p>

<?php if ($forces === []): ?>
    <p>Aucune force pour le moment.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Force</th><th>Entité racine</th><th>Validation finale locale</th><th>Achats propres</th><th>Seuils DCMA (quantité / montant)</th><?= $peutGerer ? '<th></th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($forces as $f): ?>
            <tr>
                <td><?= esc($f['nom']) ?> <span class="badge"><?= esc($f['code']) ?></span></td>
                <td><?php if ($f['racine_nom'] === null): ?><em>hors de votre périmètre</em><?php else: ?><a href="<?= site_url('organisation/entites/' . $f['entite_racine_id']) ?>"><?= esc($f['racine_nom']) ?></a><?php endif ?></td>
                <td><?= $f['validation_finale_locale'] ? 'oui' : 'non' ?></td>
                <td><?= $f['achats_propres'] ? 'oui' : 'non' ?></td>
                <td><?= esc($f['seuil_quantite_dcma'] ?? '—') ?> / <?= esc($f['seuil_montant_dcma'] ?? '—') ?></td>
                <?php if ($peutGerer): ?><td><a href="<?= site_url('organisation/forces/' . $f['id'] . '/edit') ?>">Modifier</a></td><?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
