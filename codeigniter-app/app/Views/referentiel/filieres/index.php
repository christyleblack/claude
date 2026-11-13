<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'filieres']) ?>

<h2>Filières</h2>
<p class="fil">Chaque filière est gérée par un service central (RG-05) ; elle détermine vers quel service une demande est routée.</p>
<?php if ($peutGerer): ?>
    <p><a class="bouton" href="<?= site_url('referentiel/filieres/new') ?>">Nouvelle filière</a></p>
<?php endif ?>

<?php if ($filieres === []): ?>
    <p>Aucune filière.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Filière</th><th>Code</th><th>Service gestionnaire</th><th>Articles actifs</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($filieres as $f): ?>
            <tr class="<?= $f['actif'] === 't' ? '' : 'inactif' ?>">
                <td><a href="<?= site_url('referentiel/filieres/' . $f['id'] . '/categories') ?>"><?= esc($f['libelle']) ?></a></td>
                <td><?= esc($f['code']) ?></td>
                <td><?= $f['service'] === null ? '<em>hors de votre périmètre</em>' : esc($f['service']) ?></td>
                <td><a href="<?= site_url('referentiel/articles?filiere=' . $f['id']) ?>"><?= (int) $f['nb_articles'] ?></a></td>
                <td><?php if ($peutGerer): ?><a href="<?= site_url('referentiel/filieres/' . $f['id'] . '/edit') ?>">Modifier</a><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
