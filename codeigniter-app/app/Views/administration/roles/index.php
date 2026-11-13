<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Rôles</h2>
<p><a class="bouton" href="<?= site_url('administration/roles/new') ?>">Nouveau rôle</a></p>
<p class="fil">Portée nationale : toute l'organisation. Portée entité : l'entité d'affectation et ses subordonnées hiérarchiques.</p>

<table>
    <thead><tr><th>Rôle</th><th>Code</th><th>Portée</th><th>Permissions</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($roles as $r): ?>
        <tr class="<?= $r['actif'] ? '' : 'inactif' ?>">
            <td><?= esc($r['libelle']) ?><?= $r['actif'] ? '' : ' <span class="badge">inactif</span>' ?></td>
            <td><?= esc($r['code']) ?></td>
            <td><?= $r['portee'] === 'nationale' ? 'nationale' : 'entité' ?></td>
            <td><?= $r['permissions'] === [] ? '<span class="fil">consultation de son périmètre</span>' : implode('<br>', array_map(static fn ($p) => esc($permissions[$p] ?? $p), $r['permissions'])) ?></td>
            <td><a href="<?= site_url('administration/roles/' . $r['id'] . '/edit') ?>">Modifier</a></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
