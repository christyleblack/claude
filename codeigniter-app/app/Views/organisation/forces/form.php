<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('organisation/_onglets', ['onglet' => 'forces']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <div class="grille">
        <div>
            <label for="code">Code</label>
            <input type="text" id="code" name="code" maxlength="30" required value="<?= esc(old('code', $force['code'] ?? '')) ?>">

            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" maxlength="200" required value="<?= esc(old('nom', $force['nom'] ?? '')) ?>">

            <?= view('organisation/_select', ['nom' => 'entite_racine_id', 'libelle' => 'Entité racine', 'options' => $entites, 'valeur' => $force['entite_racine_id'] ?? null]) ?>
        </div>
        <div>
            <h3>Autonomie de la direction du matériel</h3>
            <?= view('organisation/_case', ['nom' => 'validation_finale_locale', 'libelle' => 'Validation finale locale (sans remontée à la DCMA)', 'valeur' => $force['validation_finale_locale'] ?? false]) ?>
            <?= view('organisation/_case', ['nom' => 'achats_propres', 'libelle' => 'Achats propres (sans passer par le Service Approvisionnement)', 'valeur' => $force['achats_propres'] ?? false]) ?>

            <label for="seuil_quantite_dcma">Seuil de quantité au-delà duquel la DCMA intervient</label>
            <input type="text" inputmode="decimal" id="seuil_quantite_dcma" name="seuil_quantite_dcma" value="<?= esc(old('seuil_quantite_dcma', $force['seuil_quantite_dcma'] ?? '')) ?>">

            <label for="seuil_montant_dcma">Seuil de montant au-delà duquel la DCMA intervient</label>
            <input type="text" inputmode="decimal" id="seuil_montant_dcma" name="seuil_montant_dcma" value="<?= esc(old('seuil_montant_dcma', $force['seuil_montant_dcma'] ?? '')) ?>">
        </div>
    </div>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<p><a href="<?= site_url('organisation/forces') ?>">Annuler</a></p>
<?= $this->endSection() ?>
