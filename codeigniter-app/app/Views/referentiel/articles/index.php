<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'articles']) ?>

<h2>Articles</h2>
<p class="fil">Référentiel unique national, partagé par toutes les forces. Chaque force peut y ajouter sa propre fiche.</p>
<?php if (service('droits')->peut('referentiel.gerer')): ?>
    <p><a class="bouton" href="<?= site_url('referentiel/articles/new') ?>">Nouvel article</a></p>
<?php endif ?>

<form method="get" action="<?= site_url('referentiel/articles') ?>">
    <div class="grille">
        <div>
            <label for="q">Recherche (désignation ou codification)</label>
            <input type="text" id="q" name="q" value="<?= esc($filtres['q']) ?>">
            <label for="filiere">Filière</label>
            <select id="filiere" name="filiere">
                <option value="">(toutes)</option>
                <?php foreach ($filieres as $id => $libelle): ?>
                    <option value="<?= $id ?>" <?= (string) $id === $filtres['filiere'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                <option value="">(toutes)</option>
                <?php foreach ($categories as $filiere => $options): ?>
                    <optgroup label="<?= esc($filiere, 'attr') ?>">
                        <?php foreach ($options as $id => $libelle): ?>
                            <option value="<?= $id ?>" <?= (string) $id === $filtres['categorie'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                <?php endforeach ?>
            </select>
            <div class="grille" style="grid-template-columns: 1fr 1fr">
                <div>
                    <label for="mode">Suivi</label>
                    <select id="mode" name="mode">
                        <option value="">(tous)</option>
                        <?php foreach (['quantite' => 'quantité', 'lot' => 'lot', 'serie' => 'n° de série'] as $code => $libelle): ?>
                            <option value="<?= $code ?>" <?= $code === $filtres['mode'] ? 'selected' : '' ?>><?= $libelle ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label for="sensibilite">Sensibilité</label>
                    <select id="sensibilite" name="sensibilite">
                        <option value="">(toutes)</option>
                        <?php foreach (\App\Models\Referentiel\ArticleModel::SENSIBILITES as $code => $libelle): ?>
                            <option value="<?= $code ?>" <?= $code === $filtres['sensibilite'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <label class="case"><input type="checkbox" name="inactifs" value="1" <?= $filtres['inactifs'] ? 'checked' : '' ?>> Inclure les articles désactivés</label>
    <p><button class="bouton" type="submit">Rechercher</button> <a href="<?= site_url('referentiel/articles') ?>">Réinitialiser</a></p>
</form>

<?php if ($articles === []): ?>
    <p>Aucun article.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Désignation</th><th>Codification</th><th>Filière › catégorie</th><th>Unité</th><th>Suivi et sensibilité</th></tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
            <tr class="<?= $a['actif'] === 't' ? '' : 'inactif' ?>">
                <td><a href="<?= site_url('referentiel/articles/' . $a['id']) ?>"><?= esc($a['designation']) ?></a><?= $a['actif'] === 't' ? '' : ' <span class="badge">désactivé</span>' ?></td>
                <td><?= esc($a['codification']) ?></td>
                <td><?= esc($a['filiere']) ?> › <?= esc($a['categorie']) ?></td>
                <td><?= esc($a['unite_gestion']) ?></td>
                <td><?= view('referentiel/_suivi', ['mode' => $a['mode_suivi'], 'sensibilite' => $a['sensibilite']]) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p>
        <?php $q = static fn (int $p) => site_url('referentiel/articles') . '?' . http_build_query(array_filter($filtres) + ['page' => $p]) ?>
        <?php if ($page > 1): ?><a href="<?= $q($page - 1) ?>">← Précédents</a><?php endif ?>
        <?php if ($suivante): ?> <a href="<?= $q($page + 1) ?>">Suivants →</a><?php endif ?>
    </p>
<?php endif ?>
<?= $this->endSection() ?>
