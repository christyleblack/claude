<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <div class="grille">
        <div>
            <label for="code">Code</label>
            <input type="text" id="code" name="code" maxlength="40" required value="<?= esc(old('code', $role['code'] ?? '')) ?>">

            <label for="libelle">Libellé</label>
            <input type="text" id="libelle" name="libelle" maxlength="150" required value="<?= esc(old('libelle', $role['libelle'] ?? '')) ?>">

            <?= view('organisation/_select', ['nom' => 'portee', 'libelle' => 'Portée', 'options' => ['entite' => 'Entité d\'affectation et ses subordonnées', 'nationale' => 'Nationale (toute l\'organisation)'], 'valeur' => $role['portee'] ?? 'entite']) ?>

            <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Rôle actif', 'valeur' => $role['actif'] ?? true]) ?>
            <?= view('organisation/_case', ['nom' => 'double_facteur', 'libelle' => 'Rôle sensible : connexion confirmée par un code envoyé par courriel', 'valeur' => $role['double_facteur'] ?? false]) ?>
        </div>
        <div>
            <h3>Permissions</h3>
            <?php $cochees = old('_soumis') !== null ? (array) old('permissions') : $role['permissions'] ?>
            <?php foreach ($permissions as $code => $libelle): ?>
                <label class="case"><input type="checkbox" name="permissions[]" value="<?= esc($code, 'attr') ?>" <?= in_array($code, $cochees, true) ? 'checked' : '' ?>> <?= esc($libelle) ?></label>
            <?php endforeach ?>
            <p class="fil">Sans permission, le rôle donne seulement la consultation de son périmètre.</p>
        </div>
    </div>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<p><a href="<?= site_url('administration/roles') ?>">Annuler</a></p>
<?= $this->endSection() ?>
