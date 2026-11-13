<?php if ($erreurs = session()->getFlashdata('erreurs')): ?>
    <ul class="erreurs">
        <?php foreach ((array) $erreurs as $erreur): ?>
            <li><?= esc($erreur) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
