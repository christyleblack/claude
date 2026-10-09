<?php if (session('error') !== null): ?>
    <p class="erreurs"><?= esc(session('error')) ?></p>
<?php elseif (session('errors') !== null): ?>
    <ul class="erreurs">
        <?php foreach ((array) session('errors') as $erreur): ?>
            <li><?= esc($erreur) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
