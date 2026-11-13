<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>

<p class="fil">Site : <a href="<?= site_url('organisation/sites/' . $site['id']) ?>"><?= esc($site['nom']) ?></a></p>
<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">
    <input type="hidden" name="site_id" value="<?= esc((string) $site['id'], 'attr') ?>">

    <label for="code">Code</label>
    <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $emplacement['code'] ?? '')) ?>">

    <label for="libelle">Libellé</label>
    <input type="text" id="libelle" name="libelle" maxlength="200" required value="<?= esc(old('libelle', $emplacement['libelle'] ?? '')) ?>">

    <?= view('organisation/_select', ['nom' => 'parent_id', 'libelle' => 'Contenu dans', 'options' => $parents, 'valeur' => $emplacement['parent_id'] ?? null, 'vide' => true]) ?>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Emplacement actif', 'valeur' => $emplacement['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<p><a href="<?= site_url('organisation/sites/' . $site['id']) ?>">Annuler</a></p>
<?= $this->endSection() ?>
