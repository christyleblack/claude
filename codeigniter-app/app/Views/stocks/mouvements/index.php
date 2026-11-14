<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<?= view('stocks/_onglets', ['onglet' => 'stocks/mouvements']) ?>

<h2>Journal des mouvements</h2>
<p class="fil">Aucun mouvement n'est jamais modifié ni supprimé : une erreur se corrige par un mouvement d'annulation (RG-12).</p>

<form method="get" action="<?= site_url('stocks/mouvements') ?>">
    <div class="grille">
        <div>
            <label for="q">Article, lot ou n° de série</label>
            <input type="text" id="q" name="q" value="<?= esc($filtres['q']) ?>">
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">(tous)</option>
                <?php foreach (\App\Models\Stocks\MouvementModel::TYPES as $code => $libelle): ?>
                    <option value="<?= $code ?>" <?= $code === $filtres['type'] ? 'selected' : '' ?>><?= esc($libelle) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <p><button class="bouton" type="submit">Filtrer</button> <a href="<?= site_url('stocks/mouvements') ?>">Réinitialiser</a></p>
</form>

<?php if ($mouvements === []): ?>
    <p>Aucun mouvement dans votre périmètre.</p>
<?php else: ?>
    <table>
        <thead><tr><th>N°</th><th>Date</th><th>Type</th><th>Article</th><th>Quantité</th><th>De → vers</th><th>Auteur</th></tr></thead>
        <tbody>
        <?php foreach ($mouvements as $m): ?>
            <tr class="<?= $m['annule_par'] !== null ? 'inactif' : '' ?>">
                <td><a href="<?= site_url('stocks/mouvements/' . $m['id']) ?>"><?= $m['id'] ?></a></td>
                <td><?= esc(substr($m['date_mouvement'], 0, 16)) ?></td>
                <td><?= esc(\App\Models\Stocks\MouvementModel::TYPES[$m['type']]) ?><?= $m['annule_par'] !== null ? ' <span class="badge">annulé</span>' : '' ?></td>
                <td><?= esc($m['designation']) ?><?= $m['numero_serie'] !== null ? ' <span class="fil">n° ' . esc($m['numero_serie']) . '</span>' : '' ?><?= $m['numero_lot'] !== null ? ' <span class="fil">lot ' . esc($m['numero_lot']) . '</span>' : '' ?></td>
                <td><?= view('stocks/_quantite', ['q' => $m['quantite'], 'unite' => $m['unite_gestion']]) ?></td>
                <td><?= esc($m['origine'] ?? ($m['emplacement_origine_id'] === null ? '—' : 'hors périmètre')) ?> → <?= esc($m['destination'] ?? ($m['emplacement_destination_id'] === null ? '—' : 'hors périmètre')) ?></td>
                <td><?= esc($m['auteur'] ?? '') ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p>
        <?php $q = static fn (int $p) => site_url('stocks/mouvements') . '?' . http_build_query(array_filter($filtres) + ['page' => $p]) ?>
        <?php if ($page > 1): ?><a href="<?= $q($page - 1) ?>">← Plus récents</a><?php endif ?>
        <?php if ($suivante): ?> <a href="<?= $q($page + 1) ?>">Plus anciens →</a><?php endif ?>
    </p>
<?php endif ?>
<?= $this->endSection() ?>
