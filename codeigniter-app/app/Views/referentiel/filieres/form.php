<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'filieres']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <label for="code">Code</label>
    <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $filiere['code'] ?? '')) ?>">

    <label for="libelle">Libellé</label>
    <input type="text" id="libelle" name="libelle" maxlength="150" required value="<?= esc(old('libelle', $filiere['libelle'] ?? '')) ?>">

    <?= view('organisation/_select', ['nom' => 'service_gestionnaire_id', 'libelle' => 'Service gestionnaire', 'options' => $services, 'valeur' => $filiere['service_gestionnaire_id'] ?? null]) ?>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Filière active', 'valeur' => $filiere['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<p><a href="<?= site_url('referentiel/filieres') ?>">Annuler</a></p>
<?= $this->endSection() ?>
