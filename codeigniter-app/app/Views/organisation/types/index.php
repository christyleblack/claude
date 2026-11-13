<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'types']) ?>

<h2>Types d'entités</h2>
<?php $peutGerer = service('droits')->peut('organisation.gerer', null, true) ?>
<?php if ($peutGerer): ?>
    <p><a class="bouton" href="<?= site_url('organisation/types/new') ?>">Nouveau type</a></p>
<?php endif ?>

<?php if ($types === []): ?>
    <p>Aucun type pour le moment.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Libellé</th><th>Code</th><th>Stocke du matériel</th><?= $peutGerer ? '<th></th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($types as $t): ?>
            <tr>
                <td><?= esc($t['libelle']) ?></td>
                <td><?= esc($t['code']) ?></td>
                <td><?= $t['est_stockeur'] ? 'oui' : 'non' ?></td>
                <?php if ($peutGerer): ?><td><a href="<?= site_url('organisation/types/' . $t['id'] . '/edit') ?>">Modifier</a></td><?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
