<?= $this->extend('layouts/main') ?>

<?= $this->section('contenu') ?>
<p class="fil"><a href="<?= site_url('demandes') ?>">Demandes</a> › Circuits de validation</p>
<h2>Circuits de validation</h2>
<?= view('organisation/_erreurs') ?>
<p class="fil">À la soumission, la demande suit le circuit de sa filière, à défaut le circuit par défaut. Chaque étape désigne l'entité dont un valideur décide : un échelon de la chaîne logistique du demandeur (1 = parent logistique, 2 = l'échelon au-dessus…), le service gestionnaire de la filière, ou une entité désignée. Une étape avec un seuil ne s'applique que si une ligne atteint cette quantité. Une modification vaut pour les demandes soumises ensuite.</p>

<?php foreach ($circuits as $c): ?>
    <section class="carte" style="border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin: 16px 0">
        <form action="<?= site_url('demandes/circuits/' . $c['id']) ?>" method="post" class="grille">
            <?= csrf_field() ?>
            <div>
                <label for="libelle-<?= $c['id'] ?>"><?= $c['filiere'] === null ? 'Circuit par défaut' : 'Filière ' . esc($c['filiere']) ?></label>
                <input type="text" id="libelle-<?= $c['id'] ?>" name="libelle" value="<?= esc($c['libelle']) ?>">
            </div>
            <div>
                <label class="case"><input type="checkbox" name="actif" <?= $c['actif'] ? 'checked' : '' ?>> Circuit actif</label>
                <button class="bouton secondaire" type="submit">Enregistrer</button>
            </div>
        </form>

        <?php if ($c['etapes'] === []): ?>
            <p>Aucune étape : les demandes sont validées d'office à leur soumission.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Rang</th><th>Étape</th><th>Valideur</th><th>Seuil</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($c['etapes'] as $e): ?>
                    <tr>
                        <td><?= (int) $e['ordre'] ?></td>
                        <td><?= esc($e['libelle']) ?></td>
                        <td><?= match ($e['mode']) { 'logistique' => 'Échelon logistique n° ' . (int) $e['niveau'], 'filiere' => 'Service gestionnaire de la filière', default => esc($e['entite'] ?? '') } ?></td>
                        <td><?= $e['seuil_quantite'] !== null ? 'si une ligne ≥ ' . esc(rtrim(rtrim($e['seuil_quantite'], '0'), '.')) : 'toujours' ?></td>
                        <td>
                            <form action="<?= site_url('demandes/circuits/' . $c['id'] . '/etapes/' . $e['id'] . '/supprimer') ?>" method="post" onsubmit="return confirm('Supprimer cette étape ?');">
                                <?= csrf_field() ?><button class="bouton secondaire" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>

        <details>
            <summary>Ajouter une étape</summary>
            <form action="<?= site_url('demandes/circuits/' . $c['id'] . '/etapes') ?>" method="post">
                <?= csrf_field() ?>
                <div class="grille">
                    <div>
                        <label for="ordre-<?= $c['id'] ?>">Rang</label>
                        <input type="number" min="1" id="ordre-<?= $c['id'] ?>" name="ordre" value="<?= count($c['etapes']) + 1 ?>">
                        <label for="libelle-etape-<?= $c['id'] ?>">Libellé</label>
                        <input type="text" id="libelle-etape-<?= $c['id'] ?>" name="libelle" required>
                        <label for="mode-<?= $c['id'] ?>">Valideur</label>
                        <select id="mode-<?= $c['id'] ?>" name="mode">
                            <?php foreach (\App\Models\Demandes\CircuitModel::MODES as $code => $libelle): ?>
                                <option value="<?= $code ?>"><?= esc($libelle) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div>
                        <label for="niveau-<?= $c['id'] ?>">Niveau (échelon logistique)</label>
                        <input type="number" min="1" id="niveau-<?= $c['id'] ?>" name="niveau">
                        <label for="entite-<?= $c['id'] ?>">Entité (entité désignée)</label>
                        <select id="entite-<?= $c['id'] ?>" name="entite_id">
                            <option value="">(aucune)</option>
                            <?php foreach ($entites as $id => $libelle): ?>
                                <option value="<?= $id ?>"><?= esc($libelle) ?></option>
                            <?php endforeach ?>
                        </select>
                        <label for="seuil-<?= $c['id'] ?>">Seuil de quantité (facultatif)</label>
                        <input type="text" inputmode="decimal" id="seuil-<?= $c['id'] ?>" name="seuil_quantite">
                    </div>
                </div>
                <p><button class="bouton" type="submit">Ajouter l'étape</button></p>
            </form>
        </details>
    </section>
<?php endforeach ?>

<h3>Nouveau circuit propre à une filière</h3>
<form action="<?= site_url('demandes/circuits') ?>" method="post" class="grille">
    <?= csrf_field() ?>
    <div>
        <?= view('organisation/_select', ['nom' => 'filiere_id', 'libelle' => 'Filière', 'options' => $filieres, 'valeur' => null]) ?>
    </div>
    <div>
        <label for="libelle">Libellé</label>
        <input type="text" id="libelle" name="libelle" required>
        <button class="bouton" type="submit">Créer le circuit</button>
    </div>
</form>
<?= $this->endSection() ?>
