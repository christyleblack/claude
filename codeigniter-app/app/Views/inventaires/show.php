<?= $this->extend('layouts/main') ?>

<?php
$quantite = static fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 3, ',', ' '), '0'), ',');
$enCours  = $i['statut'] === 'en_cours';
$saisie   = $enCours && $estCompteur;
?>

<?= $this->section('contenu') ?>
<p class="fil"><a href="<?= site_url('inventaires') ?>">Inventaires</a> › n° <?= $i['id'] ?></p>
<h2>Inventaire n° <?= $i['id'] ?> <?= view('inventaires/_statut', ['statut' => $i['statut']]) ?></h2>

<table>
    <tr><th style="width: 30%">Type</th><td><?= esc(\App\Models\Inventaires\InventaireModel::TYPES[$i['type']]) ?></td></tr>
    <tr><th>Champ</th><td><?= esc($i['entite'] ?? '') ?><?= $i['emplacement'] !== null ? ' › ' . esc($i['emplacement']) . ' et son contenu' : ' (toute l\'entité)' ?></td></tr>
    <tr><th>Date prévue</th><td><?= esc($i['date_prevue']) ?></td></tr>
    <tr><th>Compteur</th><td><?= esc($i['compteur'] ?? '') ?></td></tr>
    <?php if ($i['type'] === 'contradictoire'): ?>
        <tr><th>Cédant / prenant</th><td><?= esc($i['cedant'] ?? '') ?> / <?= esc($i['prenant'] ?? '') ?></td></tr>
    <?php endif ?>
    <tr><th>Étapes</th><td>
        Planifié par <?= esc($i['createur'] ?? '') ?>
        <?= $i['lance_le'] !== null ? '· lancé le ' . esc(substr($i['lance_le'], 0, 16)) : '' ?>
        <?= $i['compte_le'] !== null ? '· compté le ' . esc(substr($i['compte_le'], 0, 16)) : '' ?>
        <?= $i['valide_le'] !== null ? '· validé le ' . esc(substr($i['valide_le'], 0, 16)) . ' par ' . esc($i['valideur'] ?? '') : '' ?>
        <?= $i['annule_le'] !== null ? '· annulé le ' . esc(substr($i['annule_le'], 0, 16)) : '' ?>
    </td></tr>
    <?php if ($i['observations'] !== null): ?><tr><th>Observations</th><td><?= esc($i['observations']) ?></td></tr><?php endif ?>
</table>

<div class="actions">
    <?php if ($i['statut'] === 'planifie' && $peutGerer): ?>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/lancer') ?>" method="post" onsubmit="return confirm('Lancer l\'inventaire ? Les emplacements concernés seront gelés jusqu\'à la validation.');">
            <?= csrf_field() ?><button class="bouton" type="submit">Lancer l'inventaire</button>
        </form>
    <?php endif ?>
    <?php if ($saisie): ?>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/terminer') ?>" method="post" onsubmit="return confirm('Terminer le comptage ? Il ne sera plus modifiable.');">
            <?= csrf_field() ?><button class="bouton" type="submit">Terminer le comptage</button>
        </form>
    <?php endif ?>
    <?php if ($i['statut'] === 'compte' && $i['type'] === 'contradictoire'): ?>
        <?php foreach (['cedant' => 'Signer comme cédant', 'prenant' => 'Signer comme prenant'] as $qualite => $libelle): ?>
            <?php if (! isset($signatures[$qualite]) && (int) $i[$qualite . '_id'] === $moi): ?>
                <form action="<?= site_url('inventaires/' . $i['id'] . '/signer') ?>" method="post">
                    <?= csrf_field() ?><input type="hidden" name="qualite" value="<?= $qualite ?>">
                    <button class="bouton" type="submit"><?= $libelle ?></button>
                </form>
            <?php endif ?>
        <?php endforeach ?>
    <?php endif ?>
    <?php if ($i['statut'] === 'compte' && $peutValider): ?>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/valider') ?>" method="post" onsubmit="return confirm('Valider l\'inventaire ? Chaque écart sera régularisé par un mouvement.');">
            <?= csrf_field() ?><button class="bouton" type="submit">Valider et régulariser les écarts</button>
        </form>
    <?php endif ?>
    <?php if (in_array($i['statut'], ['planifie', 'en_cours', 'compte'], true) && $peutGerer): ?>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/annuler') ?>" method="post" onsubmit="return confirm('Annuler cet inventaire ?');">
            <?= csrf_field() ?><button class="bouton danger" type="submit">Annuler l'inventaire</button>
        </form>
    <?php endif ?>
</div>

<?php if ($i['statut'] === 'compte' && ! $peutValider): ?>
    <p class="fil">En attente de validation par un détenteur du droit « valider un inventaire » autre que le compteur (RG-32).</p>
<?php endif ?>

<?php if ($i['type'] === 'contradictoire' && in_array($i['statut'], ['compte', 'valide'], true)): ?>
    <h3>Signatures (INV-03)</h3>
    <ul>
        <?php foreach (['cedant' => 'Cédant', 'prenant' => 'Prenant'] as $qualite => $libelle): ?>
            <li><?= $libelle ?> : <?= isset($signatures[$qualite]) ? esc($signatures[$qualite]['username']) . ', le ' . esc(substr($signatures[$qualite]['horodatage'], 0, 16)) : '<em>en attente</em>' ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<?php if ($i['statut'] !== 'planifie'): ?>
    <h3><?= $saisie ? 'Feuille de comptage' : 'Lignes et écarts' ?></h3>
    <?php if ($saisie): ?>
        <p class="fil">Saisissez la quantité trouvée (numéro de série : 1 présent, 0 absent). Vous pouvez enregistrer en plusieurs fois.</p>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/comptage') ?>" method="post">
        <?= csrf_field() ?>
    <?php endif ?>
    <table>
        <thead><tr><th>Emplacement</th><th>Article</th><th>Lot ou n° de série</th><th>Théorique</th><th>Compté</th><th>Écart</th><?= $i['statut'] === 'valide' ? '<th>Régularisation</th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <?php $ecart = $l['ecart'] === null ? null : (float) $l['ecart'] ?>
            <tr>
                <td><?= esc($l['emplacement_code'] ?? '') ?></td>
                <td><?= esc($l['designation']) ?> <span class="fil"><?= esc($l['codification']) ?></span><?= $l['ajoutee'] === 't' ? ' <span class="badge">trouvé au comptage</span>' : '' ?></td>
                <td><?= esc($l['numero_serie'] ?? ($l['numero_lot'] !== null ? 'lot ' . $l['numero_lot'] : '')) ?></td>
                <td><?= esc($quantite($l['quantite_theorique'])) ?> <?= esc($l['unite_gestion']) ?></td>
                <td>
                    <?php if ($saisie): ?>
                        <input type="text" inputmode="decimal" name="comptage[<?= $l['id'] ?>]" value="<?= esc($quantite($l['quantite_comptee'])) ?>" style="width: 8em">
                    <?php else: ?>
                        <?= esc($quantite($l['quantite_comptee'])) ?>
                    <?php endif ?>
                </td>
                <td style="<?= $ecart !== null && $ecart != 0 ? 'font-weight: 700; color: #b3261e' : '' ?>"><?= $ecart === null ? '' : ($ecart > 0 ? '+' : '') . esc($quantite($ecart)) ?></td>
                <?php if ($i['statut'] === 'valide'): ?>
                    <td><?= $l['mouvement_regularisation_id'] !== null ? '<a href="' . site_url('stocks/mouvements/' . $l['mouvement_regularisation_id']) . '">mouvement n° ' . (int) $l['mouvement_regularisation_id'] . '</a>' : '' ?></td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php if ($saisie): ?>
        <p><button class="bouton" type="submit">Enregistrer les comptages</button></p>
        </form>

        <h3>Ajouter un matériel trouvé, absent du stock théorique</h3>
        <form action="<?= site_url('inventaires/' . $i['id'] . '/lignes') ?>" method="post">
            <?= csrf_field() ?>
            <div class="grille">
                <div>
                    <label for="codification">Codification de l'article</label>
                    <input type="text" id="codification" name="codification" required>
                    <label for="reference">N° de lot ou de série (selon l'article)</label>
                    <input type="text" id="reference" name="reference">
                </div>
                <div>
                    <?= view('organisation/_select', ['nom' => 'emplacement_id', 'libelle' => 'Emplacement', 'options' => $emplacements, 'valeur' => null]) ?>
                    <label for="quantite">Quantité trouvée (1 pour un numéro de série)</label>
                    <input type="text" inputmode="decimal" id="quantite" name="quantite" value="1">
                </div>
            </div>
            <p><button class="bouton secondaire" type="submit">Ajouter la ligne</button></p>
        </form>
    <?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
