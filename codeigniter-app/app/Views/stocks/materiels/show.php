<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks']) ?>

<h2><?= esc($materiel['designation']) ?> n° <?= esc($materiel['numero_serie']) ?></h2>

<table>
    <tr><th style="width: 30%">Article</th><td><?= esc($materiel['codification']) ?></td></tr>
    <tr><th>État</th><td><?= esc($etats[$materiel['etat']]) ?></td></tr>
    <tr><th>Propriétaire</th><td><?= esc($materiel['proprietaire'] ?? 'hors de votre périmètre') ?></td></tr>
    <tr><th>Emplacement actuel</th><td><?= $materiel['emplacement_id'] === null ? '<em>hors stock</em>' : esc(($materiel['detenteur'] ?? '') . ' › ' . ($materiel['site'] ?? '') . ' › ' . ($materiel['emplacement'] ?? 'hors de votre périmètre')) ?></td></tr>
</table>

<?php if ($peutGerer && $materiel['etat'] !== 'reforme'): ?>
    <form action="<?= site_url('stocks/materiels/' . $materiel['id'] . '/etat') ?>" method="post" class="actions">
        <?= csrf_field() ?>
        <select name="etat" style="width: auto">
            <?php foreach (array_diff_key($etats, ['reforme' => true]) as $code => $libelle): ?>
                <option value="<?= $code ?>" <?= $code === $materiel['etat'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
            <?php endforeach ?>
        </select>
        <button class="bouton secondaire" type="submit">Changer l'état</button>
    </form>
<?php endif ?>

<h3>Historique des mouvements</h3>
<table>
    <thead><tr><th>N°</th><th>Date</th><th>Type</th><th>De → vers</th><th>Auteur</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $m): ?>
        <tr class="<?= $m['annule_par'] !== null ? 'inactif' : '' ?>">
            <td><a href="<?= site_url('stocks/mouvements/' . $m['id']) ?>"><?= $m['id'] ?></a></td>
            <td><?= esc(substr($m['date_mouvement'], 0, 16)) ?></td>
            <td><?= esc($types[$m['type']]) ?></td>
            <td><?= esc($m['origine'] ?? '—') ?> → <?= esc($m['destination'] ?? '—') ?></td>
            <td><?= esc($m['auteur'] ?? '') ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $this->endSection() ?>
