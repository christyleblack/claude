<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<p class="fil"><a href="<?= site_url('administration/utilisateurs') ?>">Utilisateurs</a> › <?= esc($utilisateur->username) ?></p>
<h2><?= esc($utilisateur->username) ?></h2>
<p><?= esc($utilisateur->email ?? '') ?></p>

<h3>Affectations</h3>
<?php if ($affectations === []): ?>
    <p class="fil">Aucune affectation : cet utilisateur ne voit aucune donnée.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Rôle</th><th>Entité</th><th>Du</th><th>Au</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($affectations as $a): ?>
            <tr>
                <td><?= esc($a['role']) ?><?= $a['portee'] === 'nationale' ? ' <span class="badge">nationale</span>' : '' ?><?= $a['filiere'] !== null ? ' <span class="badge">filière ' . esc($a['filiere']) . '</span>' : '' ?></td>
                <td><?= $a['entite'] === null ? '<em>hors de votre périmètre</em>' : esc($a['entite']) ?></td>
                <td><?= esc($a['date_debut']) ?></td>
                <td><?= esc($a['date_fin'] ?? '—') ?></td>
                <td><?= view('administration/_statut', ['statut' => $a['statut']]) ?></td>
                <td>
                    <?php if ($a['revoquee_le'] === null && $a['statut'] !== 'terminée'): ?>
                        <form action="<?= site_url('administration/affectations/' . $a['id'] . '/revoquer') ?>" method="post" onsubmit="return confirm('Révoquer cette affectation ?');">
                            <?= csrf_field() ?>
                            <button class="bouton danger" type="submit">Révoquer</button>
                        </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<h3>Nouvelle affectation</h3>
<?= view('organisation/_erreurs') ?>
<form action="<?= site_url('administration/utilisateurs/' . $utilisateur->id . '/affectations') ?>" method="post">
    <?= csrf_field() ?>
    <div class="grille">
        <div>
            <?= view('organisation/_select', ['nom' => 'role_id', 'libelle' => 'Rôle', 'options' => $roles, 'valeur' => null]) ?>
            <?= view('organisation/_select', ['nom' => 'entite_id', 'libelle' => 'Entité (le périmètre inclut ses subordonnées)', 'options' => $entites, 'valeur' => null]) ?>
            <?= view('organisation/_select', ['nom' => 'filiere_id', 'libelle' => 'Limitée à la filière (facultatif)', 'options' => $filieres, 'valeur' => null, 'vide' => true]) ?>
        </div>
        <div>
            <label for="date_debut">Début</label>
            <input type="date" id="date_debut" name="date_debut" required value="<?= esc(old('date_debut', date('Y-m-d'))) ?>">
            <label for="date_fin">Fin (facultative)</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= esc(old('date_fin', '')) ?>">
        </div>
    </div>
    <p><button class="bouton" type="submit">Affecter</button></p>
</form>
<?= $this->endSection() ?>
