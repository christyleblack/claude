<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Planifier un inventaire</h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= site_url('inventaires') ?>" method="post">
    <?= csrf_field() ?>
    <div class="grille">
        <div>
            <?= view('organisation/_select', ['nom' => 'type', 'libelle' => 'Type', 'options' => \App\Models\Inventaires\InventaireModel::TYPES, 'valeur' => 'tournant']) ?>
            <?= view('organisation/_select', ['nom' => 'entite_id', 'libelle' => 'Entité inventoriée (dépôt, magasin…)', 'options' => $entites, 'valeur' => null]) ?>
            <?= view('organisation/_select', ['nom' => 'emplacement_id', 'libelle' => 'Limité à un emplacement et à son contenu (sinon toute l\'entité)', 'options' => $emplacements, 'valeur' => null, 'vide' => true]) ?>
            <label for="date_prevue">Date prévue</label>
            <input type="date" id="date_prevue" name="date_prevue" required value="<?= esc(old('date_prevue', date('Y-m-d'))) ?>">
        </div>
        <div>
            <?= view('organisation/_select', ['nom' => 'compteur_id', 'libelle' => 'Compteur (ne pourra pas valider, RG-32)', 'options' => $utilisateurs, 'valeur' => null]) ?>
            <p class="fil">Inventaire contradictoire seulement (passation de responsabilité) :</p>
            <?= view('organisation/_select', ['nom' => 'cedant_id', 'libelle' => 'Cédant', 'options' => $utilisateurs, 'valeur' => null, 'vide' => true]) ?>
            <?= view('organisation/_select', ['nom' => 'prenant_id', 'libelle' => 'Prenant', 'options' => $utilisateurs, 'valeur' => null, 'vide' => true]) ?>
        </div>
    </div>
    <label for="observations">Observations</label>
    <textarea id="observations" name="observations" style="min-height: 70px"><?= esc(old('observations', '')) ?></textarea>
    <p><button class="bouton" type="submit">Planifier</button></p>
</form>
<p><a href="<?= site_url('inventaires') ?>">Annuler</a></p>
<?= $this->endSection() ?>
