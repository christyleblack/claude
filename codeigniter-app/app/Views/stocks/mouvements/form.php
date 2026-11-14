<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks/mouvements']) ?>

<h2>Saisir un mouvement</h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= site_url('stocks/mouvements') ?>" method="post" id="saisie">
    <?= csrf_field() ?>
    <div class="grille">
        <div>
            <?= view('organisation/_select', ['nom' => 'type', 'libelle' => 'Type de mouvement', 'options' => $types, 'valeur' => $type]) ?>

            <label for="codification">Article (codification)</label>
            <input type="text" id="codification" name="codification" list="articles" required autocomplete="off" value="<?= esc(old('codification', '')) ?>">
            <datalist id="articles">
                <?php foreach ($articles as $a): ?>
                    <option value="<?= esc($a['codification'], 'attr') ?>" data-mode="<?= esc($a['mode_suivi'], 'attr') ?>"><?= esc($a['designation']) ?> (<?= esc($a['unite_gestion']) ?>)</option>
                <?php endforeach ?>
            </datalist>

            <div data-champ="quantite">
                <label for="quantite">Quantité</label>
                <input type="text" inputmode="decimal" id="quantite" name="quantite" value="<?= esc(old('quantite', '')) ?>">
            </div>
            <div data-champ="lot">
                <label for="numero_lot">Numéro de lot</label>
                <input type="text" id="numero_lot" name="numero_lot" value="<?= esc(old('numero_lot', '')) ?>">
                <label for="date_peremption">Date de péremption (lot nouveau)</label>
                <input type="date" id="date_peremption" name="date_peremption" value="<?= esc(old('date_peremption', '')) ?>">
            </div>
            <div data-champ="serie">
                <label for="numeros_serie">Numéros de série (un par ligne : un mouvement par numéro)</label>
                <textarea id="numeros_serie" name="numeros_serie" style="min-height: 100px"><?= esc(old('numeros_serie', '')) ?></textarea>
            </div>
        </div>
        <div>
            <div data-champ="origine">
                <?= view('organisation/_select', ['nom' => 'origine', 'libelle' => 'Emplacement d\'origine', 'options' => $emplacements, 'valeur' => null, 'vide' => true]) ?>
            </div>
            <div data-champ="destination">
                <?= view('organisation/_select', ['nom' => 'destination', 'libelle' => 'Emplacement de destination', 'options' => $emplacements, 'valeur' => null, 'vide' => true]) ?>
            </div>
            <?= view('organisation/_select', ['nom' => 'proprietaire', 'libelle' => 'Propriétaire (par défaut : l\'entité qui détient l\'emplacement)', 'options' => $entites, 'valeur' => null, 'vide' => true]) ?>

            <label for="date_mouvement">Date du mouvement</label>
            <input type="datetime-local" id="date_mouvement" name="date_mouvement" value="<?= esc(old('date_mouvement', date('Y-m-d\TH:i'))) ?>">

            <label for="motif">Motif ou référence</label>
            <input type="text" id="motif" name="motif" maxlength="500" value="<?= esc(old('motif', '')) ?>">
        </div>
    </div>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>

<script>
// Affiche seulement les champs utiles ; le serveur vérifie de toute façon la saisie
(function () {
    const avecOrigine = <?= json_encode(\App\Models\Stocks\MouvementModel::AVEC_ORIGINE) ?>;
    const avecDestination = <?= json_encode(\App\Models\Stocks\MouvementModel::AVEC_DESTINATION) ?>;
    const modes = {};
    document.querySelectorAll('#articles option').forEach(o => modes[o.value] = o.dataset.mode);
    const montrer = (nom, oui) => document.querySelector('[data-champ="' + nom + '"]').style.display = oui ? '' : 'none';
    function maj() {
        const type = document.getElementById('type').value;
        const mode = modes[document.getElementById('codification').value.toUpperCase()] || null;
        montrer('origine', avecOrigine.includes(type));
        montrer('destination', avecDestination.includes(type));
        montrer('quantite', mode !== 'serie');
        montrer('lot', mode === null || mode === 'lot');
        montrer('serie', mode === null || mode === 'serie');
    }
    document.getElementById('type').addEventListener('change', maj);
    document.getElementById('codification').addEventListener('input', maj);
    maj();
})();
</script>
<?= $this->endSection() ?>
