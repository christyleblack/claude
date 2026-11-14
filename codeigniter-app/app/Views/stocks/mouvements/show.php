<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks/mouvements']) ?>

<h2>Mouvement n° <?= $m['id'] ?> : <?= esc($types[$m['type']]) ?>
    <?= $m['annule_par'] !== null ? '<span class="badge">annulé par le n° ' . (int) $m['annule_par'] . '</span>' : '' ?></h2>

<table>
    <tr><th style="width: 30%">Article</th><td><?= esc($m['designation']) ?> <span class="fil"><?= esc($m['codification']) ?></span></td></tr>
    <?php if ($m['numero_serie'] !== null): ?>
        <tr><th>Numéro de série</th><td><a href="<?= site_url('stocks/materiels/' . $m['materiel_id']) ?>"><?= esc($m['numero_serie']) ?></a></td></tr>
    <?php endif ?>
    <?php if ($m['numero_lot'] !== null): ?><tr><th>Lot</th><td><?= esc($m['numero_lot']) ?></td></tr><?php endif ?>
    <tr><th>Quantité</th><td><?= view('stocks/_quantite', ['q' => $m['quantite'], 'unite' => $m['unite_gestion']]) ?></td></tr>
    <tr><th>Origine</th><td><?= $m['emplacement_origine_id'] === null ? '—' : esc(($m['site_origine'] ?? '') . ' › ' . ($m['origine'] ?? 'hors de votre périmètre')) ?></td></tr>
    <tr><th>Destination</th><td><?= $m['emplacement_destination_id'] === null ? '—' : esc(($m['site_destination'] ?? '') . ' › ' . ($m['destination'] ?? 'hors de votre périmètre')) ?></td></tr>
    <tr><th>Propriétaire</th><td><?= esc($m['proprietaire'] ?? 'hors de votre périmètre') ?></td></tr>
    <tr><th>Date du mouvement</th><td><?= esc(substr($m['date_mouvement'], 0, 19)) ?></td></tr>
    <tr><th>Saisi par</th><td><?= esc($m['auteur'] ?? '') ?>, le <?= esc(substr($m['date_saisie'], 0, 19)) ?></td></tr>
    <?php if ($m['motif'] !== null): ?><tr><th>Motif</th><td><?= esc($m['motif']) ?></td></tr><?php endif ?>
    <?php if ($m['mouvement_annule_id'] !== null): ?>
        <tr><th>Annule</th><td><a href="<?= site_url('stocks/mouvements/' . $m['mouvement_annule_id']) ?>">mouvement n° <?= esc($m['mouvement_annule_id']) ?></a></td></tr>
    <?php endif ?>
    <?php if ($bon !== null): ?>
        <tr><th>Bon</th><td><a href="<?= site_url('stocks/bons/' . $bon['id'] . '.pdf') ?>" target="_blank"><?= esc(\App\Models\Stocks\BonModel::TYPES[$bon['type']]) ?> n° <?= esc($bon['numero']) ?> (PDF)</a></td></tr>
    <?php endif ?>
    <tr><th>Empreinte (SHA-256 chaînée)</th><td><code style="font-size: .75rem; word-break: break-all"><?= esc($m['empreinte']) ?></code></td></tr>
</table>

<?php if ($peutAnnuler): ?>
    <h3>Annuler ce mouvement</h3>
    <p class="fil">Un mouvement inverse sera enregistré et lié à celui-ci ; les deux restent dans le journal.</p>
    <form action="<?= site_url('stocks/mouvements/' . $m['id'] . '/annuler') ?>" method="post" onsubmit="return confirm('Enregistrer le mouvement inverse ?');">
        <?= csrf_field() ?>
        <label for="motif">Motif de l'annulation</label>
        <input type="text" id="motif" name="motif" required maxlength="500">
        <p><button class="bouton danger" type="submit">Annuler par un mouvement inverse</button></p>
    </form>
<?php endif ?>

<p><a href="<?= site_url('stocks/mouvements') ?>">Retour au journal</a></p>
<?= $this->endSection() ?>
