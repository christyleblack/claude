<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<p class="fil"><a href="<?= site_url('administration/mes-droits') ?>">Mes droits</a> › délégation</p>
<h2>Déléguer : <?= esc($affectation['role']) ?> — <?= esc($affectation['entite'] ?? '') ?></h2>
<p class="fil">Le délégataire exercera ce rôle sur ce périmètre pendant la période choisie, qui doit être comprise dans celle de l'affectation (du <?= esc($affectation['date_debut']) ?> au <?= esc($affectation['date_fin'] ?? 'sans fin') ?>). La délégation est tracée dans le journal d'audit.</p>
<?= view('organisation/_erreurs') ?>

<form action="<?= site_url('administration/delegations') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="affectation_id" value="<?= esc((string) $affectation['id'], 'attr') ?>">

    <?= view('organisation/_select', ['nom' => 'delegataire_id', 'libelle' => 'Délégataire', 'options' => $delegataires, 'valeur' => null]) ?>

    <div class="grille">
        <div>
            <label for="date_debut">Du</label>
            <input type="date" id="date_debut" name="date_debut" required value="<?= esc(old('date_debut', date('Y-m-d'))) ?>">
        </div>
        <div>
            <label for="date_fin">Au</label>
            <input type="date" id="date_fin" name="date_fin" required value="<?= esc(old('date_fin', '')) ?>">
        </div>
    </div>

    <label for="motif">Motif</label>
    <textarea id="motif" name="motif" required style="min-height: 80px"><?= esc(old('motif', '')) ?></textarea>

    <p><button class="bouton" type="submit">Déléguer</button></p>
</form>
<?= $this->endSection() ?>
