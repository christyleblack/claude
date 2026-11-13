<?php
/**
 * Liste déroulante.
 *
 * @var string                 $nom
 * @var string                 $libelle
 * @var array<int|string, string> $options
 * @var int|string|null        $valeur
 * @var bool                   $vide    propose une option vide (aucun)
 */
$valeur = (string) old($nom, $valeur ?? '');
?>
<label for="<?= esc($nom, 'attr') ?>"><?= esc($libelle) ?></label>
<select id="<?= esc($nom, 'attr') ?>" name="<?= esc($nom, 'attr') ?>">
    <?php if ($vide ?? false): ?>
        <option value="">(aucun)</option>
    <?php endif ?>
    <?php foreach ($options as $id => $texte): ?>
        <option value="<?= esc((string) $id, 'attr') ?>" <?= (string) $id === $valeur ? 'selected' : '' ?>><?= esc($texte) ?></option>
    <?php endforeach ?>
</select>
