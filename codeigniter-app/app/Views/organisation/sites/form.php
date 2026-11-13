<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <?= view('organisation/_select', ['nom' => 'entite_id', 'libelle' => 'Entité', 'options' => $entites, 'valeur' => $site['entite_id'] ?? null]) ?>

    <label for="code">Code</label>
    <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $site['code'] ?? '')) ?>">

    <label for="nom">Nom</label>
    <input type="text" id="nom" name="nom" maxlength="200" required value="<?= esc(old('nom', $site['nom'] ?? '')) ?>">

    <label for="adresse">Adresse</label>
    <textarea id="adresse" name="adresse" style="min-height: 80px"><?= esc(old('adresse', $site['adresse'] ?? '')) ?></textarea>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Site actif', 'valeur' => $site['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<p><a href="<?= isset($site['id']) ? site_url('organisation/sites/' . $site['id']) : site_url('organisation/entites') ?>">Annuler</a></p>
<?= $this->endSection() ?>
