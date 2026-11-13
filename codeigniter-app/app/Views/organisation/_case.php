<?php
/**
 * Case à cocher. Après une erreur de validation, l'ancienne saisie l'emporte.
 *
 * @var string $nom
 * @var string $libelle
 * @var bool   $valeur
 */
$coche = old('_soumis') !== null ? old($nom) !== null : (bool) ($valeur ?? false);
?>
<label class="case"><input type="checkbox" name="<?= esc($nom, 'attr') ?>" value="1" <?= $coche ? 'checked' : '' ?>> <?= esc($libelle) ?></label>
