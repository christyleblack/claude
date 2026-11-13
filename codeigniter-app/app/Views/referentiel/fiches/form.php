<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('referentiel/_onglets', ['onglet' => 'articles']) ?>

<p class="fil"><a href="<?= site_url('referentiel/articles/' . $article['id']) ?>"><?= esc($article['designation']) ?></a> › fiche de la force</p>
<h2><?= esc($titrePage) ?></h2>
<?= view('organisation/_erreurs') ?>

<form action="<?= esc($action, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="_soumis" value="1">
    <input type="hidden" name="article_id" value="<?= esc((string) $article['id'], 'attr') ?>">
    <input type="hidden" name="force_id" value="<?= esc((string) $force['id'], 'attr') ?>">

    <div class="grille">
        <div>
            <label for="code_local">Code propre à la force</label>
            <input type="text" id="code_local" name="code_local" maxlength="40" value="<?= esc(old('code_local', $fiche['code_local'] ?? '')) ?>">
        </div>
        <div>
            <label for="designation_locale">Désignation propre à la force</label>
            <input type="text" id="designation_locale" name="designation_locale" maxlength="250" value="<?= esc(old('designation_locale', $fiche['designation_locale'] ?? '')) ?>">
        </div>
    </div>

    <label for="attributs">Attributs propres (une ligne « nom = valeur » par attribut)</label>
    <textarea id="attributs" name="attributs" style="min-height: 120px" placeholder="seuil_alerte = 10"><?= esc(old('attributs', \App\Models\Referentiel\ArticleForceModel::attributsEnTexte($fiche['attributs'] ?? null))) ?></textarea>

    <?= view('organisation/_case', ['nom' => 'actif', 'libelle' => 'Fiche active', 'valeur' => $fiche['actif'] ?? true]) ?>

    <p><button class="bouton" type="submit">Enregistrer</button></p>
</form>
<?= $this->endSection() ?>
