<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'types']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <label for="code">Code</label>
    <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $type['code'] ?? '')) ?>">

    <label for="libelle">Libellé</label>
    <input type="text" id="libelle" name="libelle" maxlength="150" required value="<?= esc(old('libelle', $type['libelle'] ?? '')) ?>">

    <?= view('organisation/_case', ['nom' => 'est_stockeur', 'libelle' => 'Ce type d\'entité stocke du matériel (dépôt, magasin…)', 'valeur' => $type['est_stockeur'] ?? false]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<p><a href="<?= site_url('organisation/types') ?>">Annuler</a></p>
<?= $this->endSection() ?>
