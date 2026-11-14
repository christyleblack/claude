<?= $this->extend('layouts/main') ?>

<?php
$quantite = static fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 3, ',', ' '), '0'), ',');
$statutsEtape = ['en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée'];
?>

<?= $this->section('contenu') ?>
<p class="fil"><a href="<?= site_url('demandes') ?>">Demandes</a> › <?= esc($d['numero']) ?></p>
<h2>Demande <?= esc($d['numero']) ?> <?= view('demandes/_statut', ['statut' => $d['statut']]) ?><?= $d['priorite'] === 'urgente' ? ' <span class="badge" style="background:#fdecea">urgente</span>' : '' ?></h2>

<table>
    <tr><th style="width: 30%">Objet</th><td><?= esc($d['objet']) ?></td></tr>
    <tr><th>Entité demandeuse</th><td><?= esc($d['entite']) ?></td></tr>
    <tr><th>Filière</th><td><?= esc($d['filiere']) ?></td></tr>
    <tr><th>Demandeur</th><td><?= esc($d['demandeur'] ?? '') ?></td></tr>
    <?php if ($d['date_besoin'] !== null): ?><tr><th>Date de besoin</th><td><?= esc($d['date_besoin']) ?></td></tr><?php endif ?>
    <?php if ($d['entite_service_id'] !== null): ?><tr><th>Entité de service</th><td><?= esc($d['entite_service']) ?> (et ses dépôts)</td></tr><?php endif ?>
    <?php if ($d['circuit'] !== null): ?><tr><th>Circuit appliqué</th><td><?= esc($d['circuit']) ?></td></tr><?php endif ?>
</table>

<?php if ($modifiable): ?>
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('demandes/' . $d['id'] . '/edit') ?>">Modifier l'en-tête</a>
        <form action="<?= site_url('demandes/' . $d['id'] . '/soumettre') ?>" method="post" onsubmit="return confirm('Soumettre la demande ? Ses lignes ne seront plus modifiables.');">
            <?= csrf_field() ?><button class="bouton" type="submit">Soumettre la demande</button>
        </form>
    </div>
<?php endif ?>

<h3>Lignes</h3>
<?php if ($lignes === []): ?>
    <p>Aucune ligne pour le moment.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Article</th><th>Demandé</th><th>Servi</th><th>Reste</th><th>Observation</th><?= $modifiable ? '<th></th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= esc($l['designation']) ?> <span class="fil"><?= esc($l['codification']) ?></span></td>
                <td><?= esc($quantite($l['quantite'])) ?> <?= esc($l['unite_gestion']) ?></td>
                <td><?= esc($quantite($l['quantite_servie'])) ?></td>
                <td><?= esc($quantite((float) $l['quantite'] - (float) $l['quantite_servie'])) ?></td>
                <td><?= esc($l['observation'] ?? '') ?></td>
                <?php if ($modifiable): ?>
                    <td>
                        <form action="<?= site_url('demandes/' . $d['id'] . '/lignes/' . $l['id'] . '/supprimer') ?>" method="post">
                            <?= csrf_field() ?><button class="bouton secondaire" type="submit">Retirer</button>
                        </form>
                    </td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if ($modifiable): ?>
    <form action="<?= site_url('demandes/' . $d['id'] . '/lignes') ?>" method="post">
        <?= csrf_field() ?>
        <div class="grille">
            <div>
                <label for="codification">Codification de l'article (filière <?= esc($d['filiere']) ?>)</label>
                <input type="text" id="codification" name="codification" required value="<?= esc(old('codification', '')) ?>">
                <label for="quantite">Quantité</label>
                <input type="text" inputmode="decimal" id="quantite" name="quantite" required value="<?= esc(old('quantite', '')) ?>">
            </div>
            <div>
                <label for="observation">Observation</label>
                <input type="text" id="observation" name="observation" maxlength="200" value="<?= esc(old('observation', '')) ?>">
            </div>
        </div>
        <p><button class="bouton secondaire" type="submit">Ajouter la ligne</button></p>
    </form>
<?php endif ?>

<?php if ($etapes !== []): ?>
    <h3>Circuit de validation</h3>
    <table>
        <thead><tr><th>Étape</th><th>Entité</th><th>Statut</th><th>Décision</th></tr></thead>
        <tbody>
        <?php foreach ($etapes as $e): ?>
            <tr>
                <td><?= (int) $e['ordre'] ?>. <?= esc($e['libelle']) ?></td>
                <td><?= esc($e['entite']) ?></td>
                <td><?= esc($statutsEtape[$e['statut']]) ?></td>
                <td><?= $e['decideur'] !== null ? esc($e['decideur']) . ', le ' . esc(substr($e['decision_le'], 0, 16)) . ($e['commentaire'] !== null ? ' : ' . esc($e['commentaire']) : '') : '' ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if ($peutDecider): ?>
    <h3>Votre décision</h3>
    <form action="<?= site_url('demandes/' . $d['id'] . '/decider') ?>" method="post">
        <?= csrf_field() ?>
        <label for="commentaire">Commentaire (obligatoire pour un refus)</label>
        <textarea id="commentaire" name="commentaire" style="min-height: 60px"></textarea>
        <div class="actions">
            <button class="bouton" type="submit" name="decision" value="valider">Valider l'étape</button>
            <button class="bouton danger" type="submit" name="decision" value="refuser">Refuser la demande</button>
        </div>
    </form>
<?php elseif (in_array($d['statut'], ['soumise', 'en_validation'], true)): ?>
    <p class="fil">En attente de la décision d'un valideur de l'étape en cours ; le demandeur ne peut pas valider sa propre demande (RG-19).</p>
<?php endif ?>

<?php if ($peutServir): ?>
    <h3>Servir la demande</h3>
    <p class="fil">Chaque service est une perception depuis un dépôt de l'entité de service vers un emplacement de l'entité demandeuse ; un bon de perception est émis.</p>
    <form action="<?= site_url('demandes/' . $d['id'] . '/servir') ?>" method="post">
        <?= csrf_field() ?>
        <div class="grille">
            <div>
                <label for="ligne_id">Ligne</label>
                <select id="ligne_id" name="ligne_id">
                    <?php foreach ($lignes as $l): ?>
                        <?php if ((float) $l['quantite_servie'] < (float) $l['quantite']): ?>
                            <option value="<?= $l['id'] ?>"><?= esc($l['codification']) ?> – reste <?= esc($quantite((float) $l['quantite'] - (float) $l['quantite_servie'])) ?> <?= esc($l['unite_gestion']) ?> (<?= esc($l['mode_suivi']) ?>)</option>
                        <?php endif ?>
                    <?php endforeach ?>
                </select>
                <?= view('organisation/_select', ['nom' => 'origine', 'libelle' => 'Emplacement d\'origine (entité de service)', 'options' => $origines, 'valeur' => null]) ?>
                <?= view('organisation/_select', ['nom' => 'destination', 'libelle' => 'Emplacement de destination (entité demandeuse)', 'options' => $destinations, 'valeur' => null]) ?>
            </div>
            <div>
                <label for="quantite_servie">Quantité (article suivi en quantité ou par lot)</label>
                <input type="text" inputmode="decimal" id="quantite_servie" name="quantite">
                <label for="numero_lot">N° de lot (article suivi par lot)</label>
                <input type="text" id="numero_lot" name="numero_lot">
                <label for="numeros_serie">N° de série, un par ligne (article sérialisé)</label>
                <textarea id="numeros_serie" name="numeros_serie" style="min-height: 60px"></textarea>
            </div>
        </div>
        <p><button class="bouton" type="submit">Enregistrer la perception</button></p>
    </form>
<?php endif ?>

<?php if ($services !== []): ?>
    <h3>Services</h3>
    <table>
        <thead><tr><th>Date</th><th>Article</th><th>Quantité</th><th>Mouvement</th><th>Par</th></tr></thead>
        <tbody>
        <?php foreach ($services as $s): ?>
            <tr style="<?= $s['annulation_id'] !== null ? 'text-decoration: line-through; color: #888' : '' ?>">
                <td><?= esc(substr($s['servi_le'], 0, 16)) ?></td>
                <td><?= esc($s['codification']) ?></td>
                <td><?= esc($quantite($s['quantite'])) ?></td>
                <td><a href="<?= site_url('stocks/mouvements/' . $s['mouvement_id']) ?>">n° <?= (int) $s['mouvement_id'] ?></a><?= $s['annulation_id'] !== null ? ' (annulé)' : '' ?></td>
                <td><?= esc($s['serveur'] ?? '') ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if ($peutCloturer || $peutAnnuler): ?>
    <h3><?= $peutCloturer ? 'Clôturer' : 'Annuler' ?> la demande</h3>
    <?php if ($peutCloturer): ?>
        <p class="fil">La clôture arrête le service d'une demande validée ou partiellement servie (RG-17).</p>
    <?php endif ?>
    <form method="post" action="<?= site_url('demandes/' . $d['id'] . '/' . ($peutCloturer && $d['statut'] === 'en_service' ? 'cloturer' : 'annuler')) ?>">
        <?= csrf_field() ?>
        <label for="motif">Motif<?= $d['statut'] === 'brouillon' ? ' (facultatif)' : '' ?></label>
        <input type="text" id="motif" name="motif" maxlength="300">
        <div class="actions">
            <?php if ($peutCloturer): ?>
                <button class="bouton secondaire" type="submit" formaction="<?= site_url('demandes/' . $d['id'] . '/cloturer') ?>">Clôturer</button>
            <?php endif ?>
            <?php if ($peutAnnuler): ?>
                <button class="bouton danger" type="submit" formaction="<?= site_url('demandes/' . $d['id'] . '/annuler') ?>" onclick="return confirm('Annuler la demande ?');">Annuler la demande</button>
            <?php endif ?>
        </div>
    </form>
<?php endif ?>

<h3>Historique</h3>
<ul>
    <li><?= esc(substr($d['created_at'], 0, 16)) ?> : création par <?= esc($d['demandeur'] ?? '') ?></li>
    <?php foreach ($evenements as $ev): ?>
        <li><?= esc(substr($ev['horodatage'], 0, 16)) ?> : <?= esc(str_replace('_', ' ', $ev['action'])) ?> par <?= esc($ev['auteur'] ?? 'le système') ?><?= $ev['commentaire'] !== null ? ' : ' . esc($ev['commentaire']) : '' ?></li>
    <?php endforeach ?>
</ul>
<?= $this->endSection() ?>
