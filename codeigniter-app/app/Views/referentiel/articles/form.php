<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'articles']) ?>

<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">

    <div class="grille">
        <div>
            <label for="codification">Codification nationale</label>
            <input type="text" id="codification" name="codification" maxlength="40" required value="<?= esc(old('codification', $article['codification'] ?? '')) ?>">

            <label for="designation">Désignation</label>
            <input type="text" id="designation" name="designation" maxlength="250" required value="<?= esc(old('designation', $article['designation'] ?? '')) ?>">

            <?php $categorieChoisie = (string) old('categorie_id', $article['categorie_id'] ?? '') ?>
            <label for="categorie_id">Catégorie (détermine la filière, donc le service gestionnaire)</label>
            <select id="categorie_id" name="categorie_id" required>
                <option value="">(choisir)</option>
                <?php foreach ($categories as $filiere => $options): ?>
                    <optgroup label="<?= esc($filiere, 'attr') ?>">
                        <?php foreach ($options as $id => $libelle): ?>
                            <option value="<?= $id ?>" <?= (string) $id === $categorieChoisie ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <?= view('organisation/_select', ['nom' => 'unite_gestion', 'libelle' => 'Unité de gestion', 'options' => $unites, 'valeur' => $article['unite_gestion'] ?? null]) ?>
            <?= view('organisation/_select', ['nom' => 'mode_suivi', 'libelle' => 'Mode de suivi', 'options' => $modes, 'valeur' => $article['mode_suivi'] ?? null]) ?>
            <?= view('organisation/_select', ['nom' => 'sensibilite', 'libelle' => 'Sensibilité', 'options' => $sensibilites, 'valeur' => $article['sensibilite'] ?? null]) ?>
            <p class="fil">Armement : suivi par numéro de série. Munitions : par lot. Suivi par numéro de série : à l'unité. Une catégorie peut aussi imposer son mode de suivi.</p>
        </div>
    </div>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Article actif', 'valeur' => $article['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<p><a href="<?= isset($article['id']) ? site_url('referentiel/articles/' . $article['id']) : site_url('referentiel/articles') ?>">Annuler</a></p>
<?= $this->endSection() ?>
