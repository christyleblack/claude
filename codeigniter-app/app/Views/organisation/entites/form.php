<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'entites']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <div class="grille">
        <div>
            <label for="code">Code</label>
            <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $entite['code'] ?? '')) ?>">

            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" maxlength="200" required value="<?= esc(old('nom', $entite['nom'] ?? '')) ?>">

            <?= view('organisation/_select', ['nom' => 'type_entite_id', 'libelle' => 'Type', 'options' => $types, 'valeur' => $entite['type_entite_id'] ?? null]) ?>
        </div>
        <div>
            <?= view('organisation/_select', ['nom' => 'parent_hierarchique_id', 'libelle' => 'Parent hiérarchique (qui la commande)', 'options' => $parents, 'valeur' => $entite['parent_hierarchique_id'] ?? null, 'vide' => true]) ?>
            <?= view('organisation/_select', ['nom' => 'parent_logistique_id', 'libelle' => 'Parent logistique (qui la ravitaille)', 'options' => $parents, 'valeur' => $entite['parent_logistique_id'] ?? null, 'vide' => true]) ?>
            <p class="fil">La force de l'entité est déduite de sa place dans l'arbre hiérarchique.</p>
        </div>
    </div>

    <h3>Régime de responsabilité</h3>
    <?= view('organisation/_case', ['nom' => 'signature_nominative', 'libelle' => 'Signature nominative', 'valeur' => $entite['signature_nominative'] ?? false]) ?>
    <?= view('organisation/_case', ['nom' => 'inventaire_contradictoire', 'libelle' => 'Inventaire contradictoire', 'valeur' => $entite['inventaire_contradictoire'] ?? false]) ?>
    <?= view('organisation/_case', ['nom' => 'restitution_fin_engagement', 'libelle' => 'Restitution en fin d\'engagement', 'valeur' => $entite['restitution_fin_engagement'] ?? false]) ?>

    <h3>État</h3>
    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Entité active', 'valeur' => $entite['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<p><a href="<?= isset($entite['id']) ? site_url('organisation/entites/' . $entite['id']) : site_url('organisation/entites') ?>">Annuler</a></p>
<?= $this->endSection() ?>
