<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks']) ?>

<h2>Stock</h2>
<p class="fil">Calculé par la base à partir des mouvements ; limité à votre périmètre.</p>

<form method="get" action="<?= site_url('stocks') ?>">
    <div class="grille">
        <div>
            <label for="q">Article (désignation ou codification)</label>
            <input type="text" id="q" name="q" value="<?= esc($filtres['q']) ?>">
        </div>
        <div>
            <label for="entite">Entité (avec ses subordonnées)</label>
            <select id="entite" name="entite">
                <option value="">(tout mon périmètre)</option>
                <?php foreach ($entites as $id => $libelle): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtres['entite'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="onglets">
        <?php foreach (\App\Models\Stocks\StockModel::VUES as $code => $libelle): ?>
            <button class="bouton <?= $code === $vue ? '' : 'secondaire' ?>" type="submit" name="vue" value="<?= $code ?>"><?= esc($libelle) ?></button>
        <?php endforeach ?>
    </div>
</form>

<?php if ($lignes === []): ?>
    <p>Aucun stock dans ce périmètre.</p>
<?php elseif ($vue === 'nationale'): ?>
    <table>
        <thead><tr><th>Article</th><th>Codification</th><th>Quantité</th><th>Entités détentrices</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr><td><?= esc($l['designation']) ?></td><td><?= esc($l['codification']) ?></td>
                <td><?= view('stocks/_quantite', ['q' => $l['quantite'], 'unite' => $l['unite_gestion']]) ?></td><td><?= (int) $l['nb_entites'] ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php elseif ($vue === 'entite'): ?>
    <table>
        <thead><tr><th>Entité détentrice</th><th>Article</th><th>Codification</th><th>Quantité</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr><td><?= esc($l['detenteur']) ?></td><td><?= esc($l['designation']) ?></td><td><?= esc($l['codification']) ?></td>
                <td><?= view('stocks/_quantite', ['q' => $l['quantite'], 'unite' => $l['unite_gestion']]) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php else: ?>
    <table>
        <thead><tr><th>Détenteur › site › emplacement</th><th>Article</th><th>Lot ou n° de série</th><th>Quantité</th><th>Propriétaire</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= esc($l['detenteur']) ?> › <?= esc($l['site']) ?> › <?= esc($l['emplacement']) ?></td>
                <td><?= esc($l['designation']) ?> <span class="fil"><?= esc($l['codification']) ?></span></td>
                <td>
                    <?php if ($l['numero_serie'] !== null): ?>
                        <a href="<?= site_url('stocks/materiels/' . $l['materiel_id']) ?>"><?= esc($l['numero_serie']) ?></a>
                        <?= $l['etat'] !== 'en_service' ? '<span class="badge">' . esc($l['etat']) . '</span>' : '' ?>
                    <?php elseif ($l['numero_lot'] !== null): ?>
                        lot <?= esc($l['numero_lot']) ?><?= $l['date_peremption'] !== null ? ' <span class="fil">(péremption ' . esc($l['date_peremption']) . ')</span>' : '' ?>
                    <?php endif ?>
                </td>
                <td><?= view('stocks/_quantite', ['q' => $l['quantite'], 'unite' => $l['unite_gestion']]) ?></td>
                <td><?= $l['proprietaire'] === $l['detenteur'] ? '<span class="fil">détenteur</span>' : esc($l['proprietaire'] ?? 'hors de votre périmètre') ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
