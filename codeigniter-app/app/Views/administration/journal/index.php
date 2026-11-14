<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Journal d'audit</h2>
<p class="fil">Toutes les connexions et toutes les modifications des données protégées, enregistrées par la base de données. Le journal ne peut être ni modifié ni effacé.</p>

<form method="get" action="<?= site_url('administration/journal') ?>">
    <div class="grille">
        <div>
            <label for="utilisateur">Utilisateur (identifiant)</label>
            <input type="text" id="utilisateur" name="utilisateur" value="<?= esc($filtres['utilisateur']) ?>">
            <label for="objet">Objet (entite, site, affectation…)</label>
            <input type="text" id="objet" name="objet" value="<?= esc($filtres['objet']) ?>">
        </div>
        <div>
            <label for="action">Action (INSERT, UPDATE, CONNEXION, CONSULTATION…)</label>
            <input type="text" id="action" name="action" value="<?= esc($filtres['action']) ?>">
            <div class="grille" style="grid-template-columns: 1fr 1fr">
                <div><label for="du">Du</label><input type="date" id="du" name="du" value="<?= esc($filtres['du']) ?>"></div>
                <div><label for="au">Au</label><input type="date" id="au" name="au" value="<?= esc($filtres['au']) ?>"></div>
            </div>
        </div>
    </div>
    <p><button class="bouton" type="submit">Filtrer</button> <a href="<?= site_url('administration/journal') ?>">Réinitialiser</a></p>
</form>

<?php if ($lignes === []): ?>
    <p>Aucune entrée.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détails</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= esc(substr($l['horodatage'], 0, 19)) ?></td>
                <td><?= esc($l['username'] ?? ($l['utilisateur_id'] === null ? 'système' : '#' . $l['utilisateur_id'])) ?></td>
                <td><?= esc($l['action']) ?></td>
                <td><?= esc($l['objet_type'] ?? '') ?><?= $l['objet_id'] !== null ? ' #' . esc($l['objet_id']) : '' ?></td>
                <td>
                    <?php if ($l['details'] !== null): ?>
                        <details><summary>voir</summary><pre style="white-space: pre-wrap; font-size: .8rem"><?= esc(json_encode(json_decode($l['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details>
                    <?php endif ?>
                    <?= $l['adresse_ip'] !== null ? '<span class="fil">' . esc($l['adresse_ip']) . '</span>' : '' ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p>
        <?php $q = static fn (int $p) => site_url('administration/journal') . '?' . http_build_query(array_filter($filtres) + ['page' => $p]) ?>
        <?php if ($page > 1): ?><a href="<?= $q($page - 1) ?>">← Plus récents</a><?php endif ?>
        <?php if ($suivante): ?> <a href="<?= $q($page + 1) ?>">Plus anciens →</a><?php endif ?>
    </p>
<?php endif ?>
<?= $this->endSection() ?>
