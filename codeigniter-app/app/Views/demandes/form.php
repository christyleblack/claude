<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= site_url($demande === null ? 'demandes' : 'demandes/' . $demande['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="grille">
        <div>
            <?php if ($demande === null): ?>
                <?= view('organisation/_select', ['nom' => 'entite_id', 'libelle' => 'Entité demandeuse', 'options' => $entites, 'valeur' => null]) ?>
                <?= view('organisation/_select', ['nom' => 'filiere_id', 'libelle' => 'Filière (une seule par demande, RG-16)', 'options' => $filieres, 'valeur' => null]) ?>
            <?php else: ?>
                <p><strong><?= esc($demande['entite']) ?></strong>, filière <?= esc($demande['filiere']) ?></p>
            <?php endif ?>
            <label for="objet">Objet</label>
            <input type="text" id="objet" name="objet" maxlength="200" required value="<?= esc(old('objet', $demande['objet'] ?? '')) ?>">
        </div>
        <div>
            <label for="date_besoin">Date de besoin</label>
            <input type="date" id="date_besoin" name="date_besoin" value="<?= esc(old('date_besoin', $demande['date_besoin'] ?? '')) ?>">
            <?= view('organisation/_select', ['nom' => 'priorite', 'libelle' => 'Priorité', 'options' => \App\Models\Demandes\DemandeModel::PRIORITES, 'valeur' => $demande['priorite'] ?? 'normale']) ?>
        </div>
    </div>
    <p><button class="bouton" type="submit"><?= $demande === null ? 'Créer le brouillon' : 'Enregistrer' ?></button></p>
</form>
<p><a href="<?= site_url($demande === null ? 'demandes' : 'demandes/' . $demande['id']) ?>">Annuler</a></p>
<?= $this->endSection() ?>
