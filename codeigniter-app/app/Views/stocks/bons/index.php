<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks/bons']) ?>

<h2>Bons</h2>
<p class="fil">Émis automatiquement à la saisie ; numérotation continue par type et par année. Un bon émis ne se modifie pas.</p>

<form method="get" action="<?= site_url('stocks/bons') ?>" class="onglets">
    <button class="bouton <?= $type === '' ? '' : 'secondaire' ?>" type="submit" name="type" value="">Tous</button>
    <?php foreach (\App\Models\Stocks\BonModel::TYPES as $code => $libelle): ?>
        <button class="bouton <?= $code === $type ? '' : 'secondaire' ?>" type="submit" name="type" value="<?= $code ?>"><?= esc($libelle) ?></button>
    <?php endforeach ?>
</form>

<?php if ($bons === []): ?>
    <p>Aucun bon dans votre périmètre.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Numéro</th><th>Type</th><th>Émis le</th><th>Par</th><th>Lignes</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bons as $b): ?>
            <tr>
                <td><?= esc($b['numero']) ?></td>
                <td><?= esc(\App\Models\Stocks\BonModel::TYPES[$b['type']]) ?></td>
                <td><?= esc(substr($b['cree_le'], 0, 16)) ?></td>
                <td><?= esc($b['auteur'] ?? '') ?></td>
                <td><?= (int) $b['nb_lignes'] ?></td>
                <td><a href="<?= site_url('stocks/bons/' . $b['id'] . '.pdf') ?>" target="_blank">PDF</a></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p>
        <?php $q = static fn (int $p) => site_url('stocks/bons') . '?' . http_build_query(array_filter(['type' => $type, 'page' => $p])) ?>
        <?php if ($page > 1): ?><a href="<?= $q($page - 1) ?>">← Plus récents</a><?php endif ?>
        <?php if ($suivante): ?> <a href="<?= $q($page + 1) ?>">Plus anciens →</a><?php endif ?>
    </p>
<?php endif ?>
<?= $this->endSection() ?>
