<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Inventaires</h2>
<p class="fil">Planifier, lancer (quantités théoriques figées, emplacements gelés), compter, puis faire valider par une autre personne : chaque écart validé devient un mouvement de régularisation.</p>
<?php if ($peutGerer): ?>
    <p><a class="bouton" href="<?= site_url('inventaires/new') ?>">Planifier un inventaire</a></p>
<?php endif ?>

<form method="get" action="<?= site_url('inventaires') ?>" class="onglets">
    <button class="bouton <?= $statut === '' ? '' : 'secondaire' ?>" type="submit" name="statut" value="">Tous</button>
    <?php foreach (\App\Models\Inventaires\InventaireModel::STATUTS as $code => $libelle): ?>
        <button class="bouton <?= $code === $statut ? '' : 'secondaire' ?>" type="submit" name="statut" value="<?= $code ?>"><?= esc($libelle) ?></button>
    <?php endforeach ?>
</form>

<?php if ($inventaires === []): ?>
    <p>Aucun inventaire dans votre périmètre.</p>
<?php else: ?>
    <table>
        <thead><tr><th>N°</th><th>Type</th><th>Entité › emplacement</th><th>Date prévue</th><th>Compteur</th><th>Lignes</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($inventaires as $i): ?>
            <tr>
                <td><a href="<?= site_url('inventaires/' . $i['id']) ?>"><?= $i['id'] ?></a></td>
                <td><?= esc(ucfirst($i['type'])) ?></td>
                <td><?= esc($i['entite'] ?? '') ?><?= $i['emplacement'] !== null ? ' › ' . esc($i['emplacement']) : '' ?></td>
                <td><?= esc($i['date_prevue']) ?></td>
                <td><?= esc($i['compteur'] ?? '') ?></td>
                <td><?= (int) $i['nb_lignes'] ?><?= (int) $i['nb_ecarts'] > 0 ? ' <span class="badge">' . (int) $i['nb_ecarts'] . ' écart(s)</span>' : '' ?></td>
                <td><?= view('inventaires/_statut', ['statut' => $i['statut']]) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
