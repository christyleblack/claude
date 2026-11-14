<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<h2>Demandes de matériel</h2>
<p class="fil">Une demande porte sur une seule filière. Soumise, elle suit le circuit de validation de sa filière (échelon logistique, service gestionnaire…), puis l'entité de service la sert par des perceptions.</p>
<p>
    <?php if ($peutSaisir): ?><a class="bouton" href="<?= site_url('demandes/new') ?>">Nouvelle demande</a><?php endif ?>
    <?php if ($peutCircuit): ?><a class="bouton secondaire" href="<?= site_url('demandes/circuits') ?>">Circuits de validation</a><?php endif ?>
</p>

<form method="get" action="<?= site_url('demandes') ?>" class="onglets">
    <?php foreach (\App\Models\Demandes\DemandeModel::VUES as $code => $libelle): ?>
        <button class="bouton <?= $code === $vue ? '' : 'secondaire' ?>" type="submit" name="vue" value="<?= $code ?>"><?= esc($libelle) ?></button>
    <?php endforeach ?>
</form>
<form method="get" action="<?= site_url('demandes') ?>">
    <input type="hidden" name="vue" value="<?= esc($vue, 'attr') ?>">
    <label for="statut">Statut</label>
    <select id="statut" name="statut" onchange="this.form.submit()">
        <option value="">(tous)</option>
        <?php foreach (\App\Models\Demandes\DemandeModel::STATUTS as $code => $libelle): ?>
            <option value="<?= $code ?>" <?= $code === $statut ? 'selected' : '' ?>><?= esc($libelle) ?></option>
        <?php endforeach ?>
    </select>
</form>

<?php if ($demandes === []): ?>
    <p>Aucune demande.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Numéro</th><th>Objet</th><th>Entité demandeuse</th><th>Filière</th><th>Lignes</th><th>Statut</th><th>En attente de</th></tr></thead>
        <tbody>
        <?php foreach ($demandes as $d): ?>
            <tr>
                <td><a href="<?= site_url('demandes/' . $d['id']) ?>"><?= esc($d['numero']) ?></a><?= $d['priorite'] === 'urgente' ? ' <span class="badge" style="background:#fdecea">urgente</span>' : '' ?></td>
                <td><?= esc($d['objet']) ?></td>
                <td><?= esc($d['entite']) ?></td>
                <td><?= esc($d['filiere']) ?></td>
                <td><?= (int) $d['nb_lignes'] ?></td>
                <td><?= view('demandes/_statut', ['statut' => $d['statut']]) ?></td>
                <td><?= esc($d['etape_en_cours'] ?? (in_array($d['statut'], ['validee', 'en_service'], true) ? 'Service : ' . ($d['entite_service'] ?? '') : '')) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
