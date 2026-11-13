<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>

<h2>Entités</h2>

<div class="onglets">
    <a class="bouton <?= $axe === 'hierarchique' ? '' : 'secondaire' ?>" href="<?= site_url('organisation/entites?axe=hierarchique') ?>">Arbre hiérarchique</a>
    <a class="bouton <?= $axe === 'logistique' ? '' : 'secondaire' ?>" href="<?= site_url('organisation/entites?axe=logistique') ?>">Arbre logistique</a>
    <?php if (service('droits')->peut('organisation.gerer')): ?>
        <a class="bouton" href="<?= site_url('organisation/entites/new') ?>">Nouvelle entité</a>
    <?php endif ?>
</div>

<p class="fil">
    <?= $axe === 'hierarchique'
        ? 'Chaîne hiérarchique : qui commande qui.'
        : 'Chaîne logistique : qui ravitaille qui.' ?>
</p>

<?php if ($entites === []): ?>
    <p>Aucune entité dans votre périmètre. Si vous devriez en voir, demandez une affectation à un administrateur.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Entité</th><th>Code</th><th>Type</th><th>Force</th></tr></thead>
        <tbody>
        <?php foreach ($entites as $e): ?>
            <tr class="<?= $e['actif'] ? '' : 'inactif' ?>">
                <td style="padding-left: <?= 8 + 24 * $e['profondeur'] ?>px">
                    <?= $e['profondeur'] > 0 ? '└ ' : '' ?><a href="<?= site_url('organisation/entites/' . $e['id']) ?>"><?= esc($e['nom']) ?></a>
                    <?= $e['actif'] ? '' : '<span class="badge">inactive</span>' ?>
                </td>
                <td><?= esc($e['code']) ?></td>
                <td><?= esc($e['type_libelle']) ?></td>
                <td><?= esc($e['force_code'] ?? 'national') ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
