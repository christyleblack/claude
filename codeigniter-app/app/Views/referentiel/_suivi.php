<?php
$libellesSuivi = ['quantite' => 'quantité', 'lot' => 'lot', 'serie' => 'n° de série'];
$couleurs      = ['courant' => '#eee', 'sensible' => '#fff4d6', 'armement' => '#fdecea', 'munition' => '#fdecea'];
?>
<span class="badge"><?= esc($libellesSuivi[$mode] ?? $mode) ?></span>
<span class="badge" style="background: <?= $couleurs[$sensibilite] ?? '#eee' ?>"><?= esc($sensibilite) ?></span>
