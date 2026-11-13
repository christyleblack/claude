<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'filieres']) ?>

<p class="fil"><a href="<?= site_url('referentiel/filieres') ?>">Filières</a> › <?= esc($filiere['libelle']) ?></p>
<h2>Catégories : <?= esc($filiere['libelle']) ?></h2>
<?php if ($peutGerer): ?>
    <p><a class="bouton" href="<?= site_url('referentiel/categories/new?filiere=' . $filiere['id']) ?>">Nouvelle catégorie</a></p>
<?php endif ?>

<?php if ($categories === []): ?>
    <p>Aucune catégorie.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Catégorie</th><th>Code</th><th>Mode de suivi imposé</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr class="<?= $c['actif'] ? '' : 'inactif' ?>">
                <td style="padding-left: <?= 8 + 24 * $c['profondeur'] ?>px"><?= $c['profondeur'] > 0 ? '└ ' : '' ?><a href="<?= site_url('referentiel/articles?categorie=' . $c['id']) ?>"><?= esc($c['libelle']) ?></a></td>
                <td><?= esc($c['code']) ?></td>
                <td><?= $c['mode_suivi_impose'] === null ? '—' : esc($modes[$c['mode_suivi_impose']]) ?></td>
                <td><?php if ($peutGerer): ?>
                    <a href="<?= site_url('referentiel/categories/' . $c['id'] . '/edit') ?>">Modifier</a> ·
                    <a href="<?= site_url('referentiel/categories/new?filiere=' . $filiere['id'] . '&parent=' . $c['id']) ?>">Sous-catégorie</a>
                <?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
