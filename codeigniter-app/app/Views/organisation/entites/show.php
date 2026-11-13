<?= $this->extend('layouts/main') ?>

<?php
$fil = static fn (array $ancetres) => implode(' › ', array_map(
    static fn ($a) => '<a href="' . site_url('organisation/entites/' . $a['id']) . '">' . esc($a['nom']) . '</a>',
    $ancetres,
));
$lien = static fn (?array $e) => $e === null ? '<em>aucun (racine)</em>'
    : '<a href="' . site_url('organisation/entites/' . $e['id']) . '">' . esc($e['nom']) . '</a>';
$peutGerer = auth()->user()->can('organisation.gerer');
?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>

<h2><?= esc($entite['nom']) ?> <span class="badge"><?= esc($entite['code']) ?></span>
    <?= $entite['actif'] ? '' : '<span class="badge">inactive</span>' ?></h2>

<table>
    <tr><th style="width: 30%">Type</th><td><?= esc($type['libelle']) ?></td></tr>
    <tr><th>Force</th><td>
        <?php if ($force === null): ?>
            Niveau national (aucune force)
        <?php else: ?>
            <?= esc($force['nom']) ?><?= $forceRacine !== null ? ' — <strong>entité racine de la force</strong>' : '' ?>
        <?php endif ?>
    </td></tr>
    <tr><th>Parent hiérarchique</th><td><?= $lien($parentH) ?>
        <?php if ($ancetresH !== []): ?><div class="fil"><?= $fil($ancetresH) ?></div><?php endif ?></td></tr>
    <tr><th>Parent logistique</th><td><?= $lien($parentL) ?>
        <?php if ($ancetresL !== []): ?><div class="fil"><?= $fil($ancetresL) ?></div><?php endif ?></td></tr>
    <tr><th>Régime de responsabilité</th><td>
        Signature nominative : <?= $entite['signature_nominative'] ? 'oui' : 'non' ?><br>
        Inventaire contradictoire : <?= $entite['inventaire_contradictoire'] ? 'oui' : 'non' ?><br>
        Restitution en fin d'engagement : <?= $entite['restitution_fin_engagement'] ? 'oui' : 'non' ?>
    </td></tr>
</table>

<?php if ($peutGerer): ?>
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('organisation/entites/' . $entite['id'] . '/edit') ?>">Modifier</a>
        <a class="bouton" href="<?= site_url('organisation/entites/new?parent_hierarchique=' . $entite['id']) ?>">Ajouter une subordonnée</a>
        <a class="bouton" href="<?= site_url('organisation/sites/new?entite=' . $entite['id']) ?>">Ajouter un site</a>
    </div>
<?php endif ?>

<div class="grille">
    <section>
        <h3>Subordonnées hiérarchiques</h3>
        <?php if ($enfantsH === []): ?><p class="fil">Aucune.</p><?php else: ?>
            <ul><?php foreach ($enfantsH as $e): ?><li><?= $lien($e) ?></li><?php endforeach ?></ul>
        <?php endif ?>
    </section>
    <section>
        <h3>Entités ravitaillées</h3>
        <?php if ($enfantsL === []): ?><p class="fil">Aucune.</p><?php else: ?>
            <ul><?php foreach ($enfantsL as $e): ?><li><?= $lien($e) ?></li><?php endforeach ?></ul>
        <?php endif ?>
    </section>
</div>

<h3>Sites</h3>
<?php if ($sites === []): ?>
    <p class="fil">Aucun site.</p>
<?php else: ?>
    <ul>
        <?php foreach ($sites as $s): ?>
            <li class="<?= $s['actif'] ? '' : 'inactif' ?>">
                <a href="<?= site_url('organisation/sites/' . $s['id']) ?>"><?= esc($s['nom']) ?></a> <span class="badge"><?= esc($s['code']) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<p><a href="<?= site_url('organisation/entites') ?>">Retour aux entités</a></p>
<?= $this->endSection() ?>
