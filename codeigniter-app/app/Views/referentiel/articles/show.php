<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'articles']) ?>

<h2><?= esc($article['designation']) ?> <span class="badge"><?= esc($article['codification']) ?></span>
    <?= $article['actif'] ? '' : '<span class="badge">désactivé</span>' ?></h2>

<table>
    <tr><th style="width: 30%">Filière</th><td><a href="<?= site_url('referentiel/filieres/' . $filiere['id'] . '/categories') ?>"><?= esc($filiere['libelle']) ?></a></td></tr>
    <tr><th>Catégorie</th><td><?= esc($categorie['libelle']) ?></td></tr>
    <tr><th>Unité de gestion</th><td><?= esc($unite) ?></td></tr>
    <tr><th>Suivi et sensibilité</th><td><?= view('referentiel/_suivi', ['mode' => $article['mode_suivi'], 'sensibilite' => $article['sensibilite']]) ?></td></tr>
</table>

<?php if ($peutGerer): ?>
    <div class="actions"><a class="bouton secondaire" href="<?= site_url('referentiel/articles/' . $article['id'] . '/edit') ?>">Modifier</a></div>
<?php endif ?>

<h3>Fiches propres aux forces</h3>
<?php if ($fiches === []): ?>
    <p class="fil">Aucune force n'a encore de fiche pour cet article.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Force</th><th>Code local</th><th>Désignation locale</th><th>Attributs propres</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($fiches as $f): ?>
            <tr class="<?= $f['actif'] === 't' ? '' : 'inactif' ?>">
                <td><?= esc($f['force']) ?></td>
                <td><?= esc($f['code_local'] ?? '') ?></td>
                <td><?= esc($f['designation_locale'] ?? '') ?></td>
                <td class="contenu"><?= esc(\App\Models\Referentiel\ArticleForceModel::attributsEnTexte($f['attributs'])) ?></td>
                <td><?php if (service('droits')->peutForce('referentiel.fiche_force', (int) $f['force_id'])): ?><a href="<?= site_url('referentiel/fiches/' . $f['id'] . '/edit') ?>">Modifier</a><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?php foreach ($forcesAjout as $force): ?>
    <p><a class="bouton" href="<?= site_url('referentiel/fiches/new?article=' . $article['id'] . '&force=' . $force['id']) ?>">Ajouter la fiche « <?= esc($force['nom']) ?> »</a></p>
<?php endforeach ?>

<p><a href="<?= site_url('referentiel/articles') ?>">Retour aux articles</a></p>
<?= $this->endSection() ?>
