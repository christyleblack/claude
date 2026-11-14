<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Notifications</h2>
<?php if ($notifications === []): ?>
    <p>Aucune notification.</p>
<?php else: ?>
    <form action="<?= site_url('notifications/tout-lire') ?>" method="post"><?= csrf_field() ?><button class="bouton secondaire" type="submit">Tout marquer comme lu</button></form>
    <table>
        <thead><tr><th>Date</th><th>Message</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($notifications as $n): ?>
            <tr style="<?= $n['lue_le'] === null ? 'font-weight: 700' : 'color: #666' ?>">
                <td><?= esc(substr($n['cree_le'], 0, 16)) ?></td>
                <td><?= esc($n['message']) ?></td>
                <td>
                    <form action="<?= site_url('notifications/' . $n['id'] . '/lire') ?>" method="post"><?= csrf_field() ?>
                        <button class="bouton secondaire" type="submit"><?= $n['demande_id'] !== null ? 'Ouvrir ' . esc($n['numero']) : 'Marquer lue' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
