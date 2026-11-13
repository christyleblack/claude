<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>
<?php $peutGerer = service('droits')->peut('organisation.gerer', $site['entite_id']) ?>

<p class="fil"><a href="<?= site_url('organisation/entites/' . $entite['id']) ?>"><?= esc($entite['nom']) ?></a> › site</p>
<h2><?= esc($site['nom']) ?> <span class="badge"><?= esc($site['code']) ?></span>
    <?= $site['actif'] ? '' : '<span class="badge">inactif</span>' ?></h2>

<?php if ($site['adresse'] !== null && $site['adresse'] !== ''): ?>
    <p class="contenu"><?= esc($site['adresse']) ?></p>
<?php endif ?>

<?php if ($peutGerer): ?>
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('organisation/sites/' . $site['id'] . '/edit') ?>">Modifier le site</a>
        <a class="bouton" href="<?= site_url('organisation/emplacements/new?site=' . $site['id']) ?>">Ajouter un emplacement</a>
    </div>
<?php endif ?>

<h3>Emplacements de stockage</h3>
<?php if ($emplacements === []): ?>
    <p class="fil">Aucun emplacement.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Emplacement</th><th>Code</th><?= $peutGerer ? '<th></th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($emplacements as $e): ?>
            <tr class="<?= $e['actif'] ? '' : 'inactif' ?>">
                <td style="padding-left: <?= 8 + 24 * $e['profondeur'] ?>px"><?= $e['profondeur'] > 0 ? '└ ' : '' ?><?= esc($e['libelle']) ?>
                    <?= $e['actif'] ? '' : '<span class="badge">inactif</span>' ?></td>
                <td><?= esc($e['code']) ?></td>
                <?php if ($peutGerer): ?>
                    <td>
                        <a href="<?= site_url('organisation/emplacements/' . $e['id'] . '/edit') ?>">Modifier</a> ·
                        <a href="<?= site_url('organisation/emplacements/new?site=' . $site['id'] . '&parent=' . $e['id']) ?>">Ajouter dedans</a>
                    </td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
