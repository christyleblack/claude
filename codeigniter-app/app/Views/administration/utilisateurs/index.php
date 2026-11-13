<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Utilisateurs</h2>
<p class="fil">Les comptes se créent par la page d'inscription ou avec <code>php spark shield:user create</code>. Les droits viennent des affectations.</p>

<table>
    <thead><tr><th>Identifiant</th><th>Adresse e-mail</th><th>Affectations actives</th><th>Compte</th></tr></thead>
    <tbody>
    <?php foreach ($utilisateurs as $u): ?>
        <tr>
            <td><a href="<?= site_url('administration/utilisateurs/' . $u->id) ?>"><?= esc($u->username) ?></a></td>
            <td><?= esc($u->email ?? '') ?></td>
            <td><?= $actives[$u->id] ?? 0 ?></td>
            <td><?= $u->active ? 'actif' : '<span class="inactif">inactif</span>' ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
