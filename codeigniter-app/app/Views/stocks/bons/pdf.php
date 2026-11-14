<?php
$types     = \App\Models\Stocks\BonModel::TYPES;
$premiere  = $lignes[0] ?? [];
$quantite  = static fn ($q) => rtrim(rtrim(number_format((float) $q, 3, ',', ' '), '0'), ',');
$lieu      = static fn ($entite, $site, $emplacement, $id) => $id === null ? '—'
    : ($emplacement === null ? 'hors du périmètre de l\'éditeur' : $entite . ' — ' . $site . ' — ' . $emplacement);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= esc($bon['numero']) ?></title>
    <style>
        @page { margin: 18mm 15mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 2mm; }
        .numero { font-size: 12pt; font-weight: bold; }
        .entete { width: 100%; border-bottom: 1.5pt solid #111; margin-bottom: 5mm; padding-bottom: 2mm; }
        .entete td { vertical-align: top; }
        table.parties, table.lignes, table.signatures { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        table.parties td { width: 50%; border: 0.8pt solid #888; padding: 2mm 3mm; vertical-align: top; }
        .etiquette { font-size: 7.5pt; text-transform: uppercase; color: #555; }
        table.lignes th, table.lignes td { border: 0.8pt solid #888; padding: 1.5mm 2mm; text-align: left; }
        table.lignes th { background: #e8e8e8; font-size: 8pt; }
        td.nombre { text-align: right; white-space: nowrap; }
        .annule { color: #a00; font-size: 8pt; }
        table.signatures td { width: 50%; border: 0.8pt solid #888; height: 28mm; vertical-align: top; padding: 2mm 3mm; }
        .pied { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #555; border-top: 0.5pt solid #aaa; padding-top: 1mm; }
        .empreinte { font-family: "DejaVu Sans Mono", monospace; font-size: 6.5pt; color: #444; }
    </style>
</head>
<body>
    <div class="pied">
        Gestion du matériel — <?= esc($bon['numero']) ?> — édité le <?= date('d/m/Y à H:i') ?>.
        Chaque ligne renvoie à un mouvement du journal immuable ; son empreinte permet d'en vérifier l'intégrité.
    </div>

    <table class="entete">
        <tr>
            <td>
                <h1><?= esc($types[$bon['type']]) ?></h1>
                <div class="numero">N° <?= esc($bon['numero']) ?></div>
            </td>
            <td style="text-align: right">
                Émis le <?= esc(date('d/m/Y à H:i', strtotime($bon['cree_le']))) ?><br>
                par <?= esc($bon['auteur'] ?? '') ?>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="etiquette">Expéditeur</div>
                <?= esc($lieu($premiere['entite_origine'] ?? null, $premiere['site_origine'] ?? null, $premiere['origine'] ?? null, $premiere['emplacement_origine_id'] ?? null)) ?>
            </td>
            <td>
                <div class="etiquette">Destinataire</div>
                <?= esc($lieu($premiere['entite_destination'] ?? null, $premiere['site_destination'] ?? null, $premiere['destination'] ?? null, $premiere['emplacement_destination_id'] ?? null)) ?>
            </td>
        </tr>
        <tr>
            <td>
                <div class="etiquette">Propriétaire</div>
                <?= esc($premiere['proprietaire'] ?? 'hors du périmètre de l\'éditeur') ?>
            </td>
            <td>
                <div class="etiquette">Motif ou référence</div>
                <?= esc($premiere['motif'] ?? '—') ?>
            </td>
        </tr>
    </table>

    <table class="lignes">
        <thead>
            <tr><th>Mvt</th><th>Codification</th><th>Désignation</th><th>Lot ou n° de série</th><th>Quantité</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= (int) $l['id'] ?></td>
                <td><?= esc($l['codification']) ?></td>
                <td>
                    <?= esc($l['designation']) ?>
                    <?php if ($l['annule_par'] !== null): ?><br><span class="annule">Annulé par le mouvement n° <?= (int) $l['annule_par'] ?></span><?php endif ?>
                    <br><span class="empreinte"><?= esc(substr($l['empreinte'], 0, 32)) ?>…</span>
                </td>
                <td><?= esc($l['numero_serie'] ?? ($l['numero_lot'] !== null ? 'lot ' . $l['numero_lot'] : '')) ?></td>
                <td class="nombre"><?= esc($quantite($l['quantite'])) ?> <?= esc($l['unite_gestion']) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="etiquette">Remis par (nom, grade, date, signature)</div></td>
            <td><div class="etiquette">Reçu par (nom, grade, date, signature)</div></td>
        </tr>
    </table>
</body>
</html>
