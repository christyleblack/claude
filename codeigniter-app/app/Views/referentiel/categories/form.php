<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'filieres']) ?>

<p class="fil"><a href="<?= site_url('referentiel/filieres/' . $filiere['id'] . '/categories') ?>"><?= esc($filiere['libelle']) ?></a> › catégorie</p>
<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">
    <input type="hidden" name="filiere_id" value="<?= esc((string) $filiere['id'], 'attr') ?>">

    <label for="code">Code</label>
    <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $categorie['code'] ?? '')) ?>">

    <label for="libelle">Libellé</label>
    <input type="text" id="libelle" name="libelle" maxlength="150" required value="<?= esc(old('libelle', $categorie['libelle'] ?? '')) ?>">

    <?= view('organisation/_select', ['nom' => 'parent_id', 'libelle' => 'Sous-catégorie de', 'options' => $parents, 'valeur' => $categorie['parent_id'] ?? null, 'vide' => true]) ?>
    <?= view('organisation/_select', ['nom' => 'mode_suivi_impose', 'libelle' => 'Mode de suivi imposé aux articles (RG-07)', 'options' => $modes, 'valeur' => $categorie['mode_suivi_impose'] ?? null, 'vide' => true]) ?>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Catégorie active', 'valeur' => $categorie['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<?= $this->endSection() ?>
