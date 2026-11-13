<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Mes droits</h2>

<h3>Droits actifs aujourd'hui</h3>
<?php if ($actives === []): ?>
    <p>Vous n'avez aucune affectation active : aucune donnée ne vous est accessible. Demandez une affectation à un administrateur.</p>
<?php else: ?>
    <ul>
        <?php foreach ($actives as $a): ?>
            <li><?= esc($a['role']) ?> — <?= esc($a['entite']) ?><?= $a['portee'] === 'nationale' ? ' (portée nationale)' : ' et ses subordonnées' ?>
                <?= $a['par_delegation'] === 't' ? '<span class="badge">par délégation</span>' : '' ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<h3>Mes affectations</h3>
<?php if ($affectations === []): ?>
    <p class="fil">Aucune.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Rôle</th><th>Entité</th><th>Du</th><th>Au</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($affectations as $a): ?>
            <tr>
                <td><?= esc($a['role']) ?></td>
                <td><?= esc($a['entite'] ?? '') ?></td>
                <td><?= esc($a['date_debut']) ?></td>
                <td><?= esc($a['date_fin'] ?? '—') ?></td>
                <td><?= view('administration/_statut', ['statut' => $a['statut']]) ?></td>
                <td><?php if (in_array($a['statut'], ['active', 'à venir'], true)): ?><a href="<?= site_url('administration/delegations/new?affectation=' . $a['id']) ?>">Déléguer</a><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<h3>Délégations</h3>
<?php if ($delegations === []): ?>
    <p class="fil">Aucune délégation donnée ni reçue.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Rôle délégué</th><th>De</th><th>À</th><th>Période</th><th>Motif</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($delegations as $d): ?>
            <tr>
                <td><?= esc($d['role']) ?> — <?= esc($d['entite'] ?? '') ?></td>
                <td><?= esc($d['titulaire']) ?></td>
                <td><?= esc($d['delegataire']) ?></td>
                <td><?= esc($d['date_debut']) ?> → <?= esc($d['date_fin']) ?></td>
                <td><?= esc($d['motif']) ?></td>
                <td><?= view('administration/_statut', ['statut' => $d['statut']]) ?></td>
                <td>
                    <?php if ($d['revoquee_le'] === null && (int) $d['titulaire_id'] === (int) auth()->id() && $d['statut'] !== 'terminée'): ?>
                        <form action="<?= site_url('administration/delegations/' . $d['id'] . '/revoquer') ?>" method="post" onsubmit="return confirm('Révoquer cette délégation ?');">
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
<?= $this->endSection() ?>
